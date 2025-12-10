<?php

namespace App\Http\Controllers;

use App\Models\JobVacancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $query = JobVacancy::query();
        if ($request->has('search') && $request->has('filter')) {
            $query->where(function (Builder $q) use ($request): void {
                $q->where('title', 'like', '%' . $request->search . '%')
                    ->orWhere('location', 'like', '%' . $request->search . '%')
                    ->orWhereHas('company', function (Builder $q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%');
                    });
            })
                ->where('type', $request->filter);
        }

        if ($request->has('search') && $request->input('filter') === null) {
            $query->where('title', 'like', '%' . $request->input('search') . '%')
                ->orWhere('location', 'like', '%' . $request->input('search') . '%')
                ->orWhere('salary', 'like', '%' . $request->input('search') . '%')
                ->orWhereHas('company', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->input('search') . '%');
                });
        }

        if ($request->has('filter') && $request->input('search') === null) {
            $query->where('type', $request->input('filter'));
        }

        $jobs = $query->latest()->paginate(10)->withQueryString();
        return view('dashboard', compact('jobs'));
    }
}
