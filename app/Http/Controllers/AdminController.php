<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Services\HttpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AdminController extends Controller
{
    protected $httpService;

    public function __construct(HttpService $httpService)
    {
        $this->httpService = $httpService;
    }

    public function dashboard(Request $request)
    {
        $adminRequests = User::where('is_admin', null)->get();
        $revisorRequests = User::where('is_revisor', null)->get();
        $writerRequests = User::where('is_writer', null)->get();
        $financialData = ['users' => []];

        try {
            $financialData = $this->httpService->fetchFinancialDataForAdmin($request->user());
        } catch (RuntimeException) {
            Log::error('Financial App data retrieval failed', [
                'event' => 'financial_data_retrieval_failed',
                'actor_user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
                'result' => 'failed',
            ]);
        }

        return view('admin.dashboard', compact('adminRequests', 'revisorRequests', 'writerRequests', 'financialData'));
    }

    public function setAdmin(Request $request, User $user)
    {
        $this->ensureCanChangeRole($request, $user, 'admin');

        $user->is_admin = true;
        $user->save();

        $this->logRoleAssigned($request, $user, 'admin');

        return redirect(route('admin.dashboard'))->with('message', "$user->name is now administrator");
    }

    public function setRevisor(Request $request, User $user)
    {
        $this->ensureCanChangeRole($request, $user, 'revisor');

        $user->is_revisor = true;
        $user->save();

        $this->logRoleAssigned($request, $user, 'revisor');

        return redirect(route('admin.dashboard'))->with('message', "$user->name is now revisor");
    }

    public function setWriter(Request $request, User $user)
    {
        $this->ensureCanChangeRole($request, $user, 'writer');

        $user->is_writer = true;
        $user->save();

        $this->logRoleAssigned($request, $user, 'writer');

        return redirect(route('admin.dashboard'))->with('message', "$user->name is now writer");
    }

    public function editTag(Request $request, Tag $tag)
    {
        $request->validate([
            'name' => 'required|unique:tags',
        ]);
        $tag->update([
            'name' => strtolower($request->name),
        ]);

        return redirect()->back()->with('message', 'Tag successfully updated');
    }

    public function deleteTag(Tag $tag)
    {
        foreach ($tag->articles as $article) {
            $article->tags()->detach($tag);
        }
        $tag->delete();

        return redirect()->back()->with('message', 'Tag successfully deleted');
    }

    public function editCategory(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|unique:categories',
        ]);
        $category->update([
            'name' => strtolower($request->name),
        ]);

        return redirect()->back()->with('message', 'Category successfully updated');
    }

    public function deleteCategory(Category $category)
    {
        $category->delete();

        return redirect()->back()->with('message', 'Category successfully deleted');
    }

    public function storeCategory(Request $request)
    {
        Category::create([
            'name' => strtolower($request->name),
        ]);

        return redirect()->back()->with('message', 'Category successfully created');
    }

    public function storeTag(Request $request)
    {
        Tag::create([
            'name' => strtolower($request->name),
        ]);

        return redirect()->back()->with('message', 'Tag successfully created');
    }

    private function ensureCanChangeRole(Request $request, User $targetUser, string $role): void
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return;
        }

        Log::warning('Unauthorized role change attempt', [
            'event' => 'unauthorized_role_change',
            'actor_user_id' => Auth::id(),
            'target_user_id' => $targetUser->id,
            'role' => $role,
            'action' => 'assign',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->route()?->getName() ?? $request->path(),
            'method' => $request->method(),
            'result' => 'denied',
        ]);

        abort(403);
    }

    private function logRoleAssigned(Request $request, User $targetUser, string $role): void
    {
        Log::notice('User role assigned', [
            'event' => 'role_assigned',
            'actor_user_id' => Auth::id(),
            'target_user_id' => $targetUser->id,
            'role' => $role,
            'action' => 'assign',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'result' => 'success',
        ]);
    }
}
