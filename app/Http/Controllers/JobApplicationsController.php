<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;

class JobApplicationsController extends Controller
{
    public function index()
    {
        $jobApplications = JobApplication::where('user_id', auth()->id())->latest()->paginate(10);
        return view('job-applications.index', compact('jobApplications'));
    }
}
