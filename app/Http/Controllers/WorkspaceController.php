<?php

namespace App\Http\Controllers;

use App\Models\UserFavorite;
use App\Models\UserRecentView;
use App\Support\Navigation;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function apps(Request $request, CurrentCompany $current)
    {
        $favorites = UserFavorite::where('user_id', $request->user()->id)->orderBy('sort')->orderBy('id')->get();
        $recents = UserRecentView::where('user_id', $request->user()->id)->orderByDesc('visited_at')->limit(8)->get();

        return view('apps', [
            'company' => $current->get(),
            'workspaces' => Navigation::groups($request->user(), $current->id()),
            'favorites' => $favorites,
            'recents' => $recents,
        ]);
    }

    public function toggleFavorite(Request $request)
    {
        $data = $request->validate(['label' => ['required', 'max:160'], 'href' => ['required', 'max:500']]);
        abort_if(! str_starts_with($data['href'], '/'), 422);
        $existing = UserFavorite::where('user_id', $request->user()->id)->where('href', $data['href'])->first();
        if ($existing) {
            $existing->delete();

            return response()->json(['favorited' => false]);
        }
        UserFavorite::create(['user_id' => $request->user()->id, ...$data]);

        return response()->json(['favorited' => true]);
    }

    public function recordRecent(Request $request)
    {
        $data = $request->validate(['label' => ['required', 'max:160'], 'href' => ['required', 'max:500']]);
        abort_if(! str_starts_with($data['href'], '/'), 422);
        if (str_starts_with($data['href'], '/admin/preferences')) {
            return response()->noContent();
        }
        UserRecentView::updateOrCreate(
            ['user_id' => $request->user()->id, 'href' => $data['href']],
            ['label' => $data['label'], 'visited_at' => now()]
        );
        $stale = UserRecentView::where('user_id', $request->user()->id)->orderByDesc('visited_at')->pluck('id')->skip(40);
        if ($stale->isNotEmpty()) {
            UserRecentView::whereIn('id', $stale)->delete();
        }

        return response()->noContent();
    }
}
