<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SiteContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        return response()->json(['clients' => User::where('role', 'client')->orderBy('name')->get(), 'bookings' => DB::table('bookings')->orderByDesc('date')->get(), 'content' => SiteContent::all()]);
    }

    public function client(Request $request, ?int $id = null)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)], 'organization' => 'required|string|max:255']);
        if ($id) {
            $user = User::where('role', 'client')->findOrFail($id);
            $user->update($data);
        } else {
            $user = new User($data + ['password' => Hash::make(Str::random(64))]);
            $user->save();
        }

        return response()->json(['user' => $user], $id ? 200 : 201);
    }

    public function invite(Request $request, int $id)
    {
        $user = User::where('role', 'client')->findOrFail($id);
        if (! $user->invitation_token && $user->email_verified_at) {
            throw ValidationException::withMessages(['client' => 'This client is already active. Use password reset instead.']);
        }
        $token = Str::random(64);
        $user->invitation_token = hash('sha256', $token);
        $user->invitation_expires_at = now()->addDays(7);
        $user->save();
        $url = url('/invite/'.$token);
        $sent = false;
        if ($request->boolean('send_email')) {
            try {
                Mail::raw('You have been invited to the PixelForge client portal. Set your password using this link within seven days: '.$url, fn ($mail) => $mail->to($user->email)->subject('Your PixelForge client portal invitation'));
                $sent = true;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['url' => $url, 'expires_at' => $user->invitation_expires_at, 'email_dispatched' => $sent, 'mailer' => config('mail.default'), 'message' => $request->boolean('send_email') && ! $sent ? 'Invitation created, but email delivery failed. Copy the link or retry.' : 'Invitation link created.']);
    }

    public function founderImage(Request $request)
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048']);
        $path = $request->file('image')->store('founder', 'public');
        $about = SiteContent::all()['about'];
        $about['founder_image'] = '/storage/'.$path;
        DB::table('site_settings')->updateOrInsert(['key' => 'about'], ['value' => json_encode($about), 'updated_at' => now(), 'created_at' => now()]);

        return response()->json(['url' => $about['founder_image']]);
    }

    private function resource(string $resource): array
    {
        $client = ['required', 'integer', Rule::exists('users', 'id')->where('role', 'client')];

        return match ($resource) {
            'projects' => ['projects', ['client_id' => $client, 'name' => 'required|string|max:255', 'description' => 'required|string|max:10000', 'status' => ['required', Rule::in(['planning', 'in_progress', 'review', 'completed', 'archived'])], 'target_date' => 'nullable|date_format:Y-m-d']],
            'quotations' => ['quotations', ['client_id' => $client, 'title' => 'required|string|max:255', 'scope' => 'required|string|max:20000', 'amount' => 'required|numeric|min:0|max:9999999999.99', 'valid_until' => 'required|date_format:Y-m-d', 'status' => ['required', Rule::in(['draft', 'sent'])]]],
            'milestones' => ['milestones', ['project_id' => 'required|integer|exists:projects,id', 'title' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'target_date' => 'nullable|date_format:Y-m-d', 'status' => ['required', Rule::in(['planned', 'in_progress', 'ready_for_review'])], 'position' => 'required|integer|min:0|max:10000']],
            'updates' => ['project_updates', ['project_id' => 'required|integer|exists:projects,id', 'body' => 'required|string|max:10000']],
            'invoices' => ['invoices', ['project_id' => 'required|integer|exists:projects,id', 'reference' => 'required|string|max:255', 'description' => 'required|string|max:255', 'amount' => 'required|numeric|min:0|max:9999999999.99', 'due_date' => 'required|date_format:Y-m-d', 'status' => ['required', Rule::in(['draft', 'sent', 'paid', 'cancelled'])]]],
            default => abort(404),
        };
    }

    public function save(Request $request, string $resource, ?int $id = null)
    {
        [$table,$rules] = $this->resource($resource);
        if ($resource === 'invoices') {
            $rules['reference'] = ['required', 'string', 'max:255', Rule::unique('invoices')->ignore($id)];
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $resource, $table, $id, &$data) {
            $existing = $id ? DB::table($table)->where('id', $id)->lockForUpdate()->first() : null;
            if ($id) {
                abort_unless($existing, 404);
            }
            if ($existing && $resource === 'quotations' && in_array($existing->status, ['accepted', 'declined'])) {
                throw ValidationException::withMessages(['status' => 'Answered quotations are retained as agreed. Create a new quotation for changes.']);
            }
            if ($existing && $resource === 'projects' && $existing->quotation_id && $existing->client_id != $data['client_id']) {
                throw ValidationException::withMessages(['client_id' => 'A quotation-linked project cannot be transferred to another client.']);
            }
            if ($existing && in_array($resource, ['milestones', 'invoices', 'updates']) && $existing->project_id != $data['project_id']) {
                throw ValidationException::withMessages(['project_id' => 'Existing project records cannot be transferred.']);
            }
            if ($resource === 'updates' && ! $id) {
                $data['user_id'] = $request->user()->id;
            }
            if ($resource === 'milestones') {
                $data['approved_at'] = null;
            }
            $data['updated_at'] = now();
            if ($id) {
                DB::table($table)->where('id', $id)->update($data);
            } else {
                $data['created_at'] = now();
                $data['id'] = DB::table($table)->insertGetId($data);
            }
        });

        return response()->json(['message' => 'Saved.', 'id' => $id ?: $data['id']], $id ? 200 : 201);
    }

    public function content(Request $request)
    {
        $defaults = SiteContent::defaults();
        $rules = ['home' => 'required|array', 'about' => 'required|array', 'booking' => 'required|array', 'maintenance' => 'required|array', 'services' => 'required|array|min:1|max:20', 'case_studies' => 'present|array|max:50'];
        foreach (['home', 'about', 'booking', 'maintenance'] as $section) {
            $rules[$section] = 'required|array:'.implode(',', array_keys($defaults[$section]));
            foreach ($defaults[$section] as $field => $value) {
                $rules[$section.'.'.$field] = 'required|string|max:5000';
            }
        }
        foreach (['services' => ['icon', 'title', 'description'], 'case_studies' => ['title', 'description']] as $section => $fields) {
            $rules[$section.'.*'] = 'array:'.implode(',', $fields);
            foreach ($fields as $field) {
                $rules[$section.'.*.'.$field] = 'required|string|max:5000';
            }
        }
        $rules['about.founder_image'] = ['required', 'string', 'regex:#^/(?:images|storage/founder)/[a-zA-Z0-9._/-]+$#'];
        $data = $request->validate($rules);
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('site_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
            }
        });

        return response()->json(['message' => 'Website content published.']);
    }
}
