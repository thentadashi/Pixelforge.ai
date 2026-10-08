<?php

namespace App\Http\Controllers;

use App\Services\ProjectAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $projects = DB::table('projects')->when(! $user->isAdmin(), fn ($q) => $q->where('client_id', $user->id))->orderByDesc('id')->get();
        $ids = $projects->pluck('id');
        $milestones = DB::table('milestones')->whereIn('project_id', $ids)->orderBy('position')->get();
        $tickets = DB::table('tickets')->whereIn('project_id', $ids)->orderByDesc('id')->get();

        return response()->json([
            'projects' => $projects, 'milestones' => $milestones, 'tickets' => $tickets,
            'updates' => DB::table('project_updates')->whereIn('project_id', $ids)->orderByDesc('id')->get(),
            'revisions' => DB::table('revisions')->whereIn('milestone_id', $milestones->pluck('id'))->get(),
            'replies' => DB::table('ticket_replies')->join('users', 'users.id', '=', 'ticket_replies.user_id')->whereIn('ticket_id', $tickets->pluck('id'))->select('ticket_replies.*', 'users.name as author')->orderBy('ticket_replies.id')->get(),
            'files' => DB::table('project_files')->whereIn('project_id', $ids)->select('id', 'project_id', 'user_id', 'name', 'size', 'mime', 'created_at')->get(),
            'invoices' => DB::table('invoices')->whereIn('project_id', $ids)->when(! $user->isAdmin(), fn ($q) => $q->where('status', '!=', 'draft'))->get(),
            'quotations' => DB::table('quotations')->when(! $user->isAdmin(), fn ($q) => $q->where('client_id', $user->id)->where('status', '!=', 'draft'))->orderByDesc('id')->get(),
        ]);
    }

    public function quotationDecision(Request $request, int $id)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['accepted', 'declined'])]]);
        DB::transaction(function () use ($request, $id, $data) {
            $quote = DB::table('quotations')->where('id', $id)->where('client_id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($quote, 404);
            if ($quote->status !== 'sent') {
                throw ValidationException::withMessages(['decision' => 'This quotation has already been answered or is not ready for acceptance.']);
            }
            if (Carbon::parse($quote->valid_until)->endOfDay()->isPast()) {
                throw ValidationException::withMessages(['decision' => 'This quotation has expired. Ask PixelForge for an updated quotation.']);
            }
            DB::table('quotations')->where('id', $id)->update(['status' => $data['decision'], 'responded_at' => now(), 'updated_at' => now()]);
            if ($data['decision'] === 'accepted') {
                DB::table('projects')->insert(['client_id' => $quote->client_id, 'quotation_id' => $id, 'name' => $quote->title, 'description' => $quote->scope, 'status' => 'planning', 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['message' => 'Quotation '.$data['decision'].'.']);
    }

    public function milestoneDecision(Request $request, int $id)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approved', 'revision_requested'])], 'note' => 'required_if:decision,revision_requested|nullable|string|max:10000']);
        DB::transaction(function () use ($request, $id, $data) {
            $milestone = DB::table('milestones')->where('id', $id)->lockForUpdate()->first();
            abort_unless($milestone, 404);
            $project = ProjectAccess::find($request->user(), $milestone->project_id);
            abort_unless($project->client_id === $request->user()->id, 403);
            if ($milestone->status !== 'ready_for_review') {
                throw ValidationException::withMessages(['decision' => 'Only deliverables ready for review can receive a decision.']);
            }
            DB::table('milestones')->where('id', $id)->update(['status' => $data['decision'], 'approved_at' => $data['decision'] === 'approved' ? now() : null, 'updated_at' => now()]);
            if ($data['decision'] === 'revision_requested') {
                DB::table('revisions')->insert(['milestone_id' => $id, 'user_id' => $request->user()->id, 'note' => $data['note'], 'created_at' => now(), 'updated_at' => now()]);
            }
        });

        return response()->json(['message' => 'Deliverable decision saved.']);
    }

    public function ticket(Request $request)
    {
        $data = $request->validate(['project_id' => 'required|integer', 'subject' => 'required|string|max:255', 'details' => 'required|string|max:10000']);
        ProjectAccess::find($request->user(), $data['project_id']);
        $data += ['user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()];
        $id = DB::table('tickets')->insertGetId($data);

        return response()->json(['id' => $id], 201);
    }

    public function reply(Request $request, int $id)
    {
        $ticket = DB::table('tickets')->find($id);
        abort_unless($ticket, 404);
        ProjectAccess::find($request->user(), $ticket->project_id);
        $data = $request->validate(['body' => 'required|string|max:10000']);
        $data += ['ticket_id' => $id, 'user_id' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()];
        DB::table('ticket_replies')->insert($data);

        return response()->json(['message' => 'Reply posted.'], 201);
    }

    public function ticketStatus(Request $request, int $id)
    {
        $ticket = DB::table('tickets')->find($id);
        abort_unless($ticket, 404);
        ProjectAccess::find($request->user(), $ticket->project_id);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'resolved'])]]);
        DB::table('tickets')->where('id',$id)->update($data + ['updated_at' => now()]);

        return response()->json(['message' => 'Ticket updated.']);
    }
}
