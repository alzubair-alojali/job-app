<?php

use App\Http\Controllers\jobVacancyController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JobApplicationsController;
Route::get('/', function () {
    return view('welcome');
});



Route::middleware(['auth', 'role:job-seeker'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/job-applications', [JobApplicationsController::class, 'index'])->name('job-applications.index');
    Route::get('/job-vacancies/{id}', [jobVacancyController::class, 'show'])->name('job-vacancies.show');
    Route::get('/job-vacancies/apply/{id}', [jobVacancyController::class, 'apply'])->name('job-vacancies.apply');
    Route::post('/job-vacancies/apply/{id}', [jobVacancyController::class, 'processApplication'])->name('job-vacancies.process-application');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    //test Gemini
    Route::get('/test-gemini', [jobVacancyController::class, 'testGemini'])->name('test-gemini');
});

require __DIR__.'/auth.php';
