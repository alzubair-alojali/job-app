<?php

namespace App\Http\Controllers;
use App\Http\Requests\applyJobRequest;
use App\Models\JobApplication;
use App\Services\ResumeAnalysisService;
use Gemini\Laravel\Facades\Gemini;
use App\Models\JobVacancy;
use App\Models\Resume;
use Illuminate\Http\Request;

class jobVacancyController extends Controller
{

    protected $resumeAnalysisService;

    public function __construct(ResumeAnalysisService $resumeAnalysisService)
    {
        $this->resumeAnalysisService = $resumeAnalysisService;
    }
    public function show(string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        return view('job-vacancies.show', compact('jobVacancy'));
    }
    public function apply(string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        $resumes = auth()->user()->resumes;
        return view('job-vacancies.apply', compact('jobVacancy', 'resumes'));
    }
    public function processApplication(applyJobRequest $request, string $id)
    {
        $jobVacancy = JobVacancy::findOrFail($id);
        $extractedinfo = null;
        $resumeId = null;
        if ($request->input('resume_option') === 'new_resume') {
            // Validate and process the uploaded resume file
            $file = $request->file('resume_file');
            $extension = $file->getClientOriginalExtension();
            $originalFileName = $file->getClientOriginalName();
            $fileName = 'resume_' . time() . '.' . $extension;
            // Store in supabase cloud
            $path = $file->storeAs('resumes', $fileName, 'cloud');
            $fileUrl = config('filesystems.disks.cloud.url') . '/' . $path;

            //ToDO: Extract data from resume using Gemini API (not implemented here)
            $extractedinfo = $this->resumeAnalysisService->extractResumeInformation($fileUrl);
            $resume = Resume::create([
                'file_name' => $originalFileName,
                'file_uri' => $path,
                'user_id' => auth()->id(),
                'contact_details' => json_encode([
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                    'phone' => $request->input('phone'),
                ]),
                'education' => $extractedinfo['education'],
                'experience' => $extractedinfo['experience'],
                'skills' => $extractedinfo['skills'],
                'summary' => $extractedinfo['summary'],
            ]);

            $resumeId = $resume->id;


        } else {
            // Use existing resume
            $resumeId = $request->input('resume_option');
            $resume = Resume::findOrFail($resumeId);
            $extractedinfo = [
                'education' => $resume->education,
                'experience' => $resume->experience,
                'skills' => $resume->skills,
                'summary' => $resume->summary,
            ];
        }

        //TODO : evaluate application using Gemini API (not implemented here)

        $evaluationResult = $this->resumeAnalysisService->analyzeResume($jobVacancy, $extractedinfo);
        JobApplication::create([
            'status' => 'pending',
            'aigeneratedScore' => $evaluationResult['aiGeneratedScore'],
            'aigeneratedFeedback' => $evaluationResult['aiGeneratedFeedback'],
            'job_vacancy_id' => $id,
            'user_id' => auth()->id(),
            'resume_id' => $resume->id,
        ]);
        // Logic to process job application with the $resume
        return redirect()->route('job-applications.index', $id)->with('success', 'Application submitted successfully.');
    }


    public function testGemini()
    {
        $request = Gemini::generativeModel(model: 'gemini-2.0-flash')->generateContent('Explain quantum physics to a 5-year-old.');
        $result = $request->text();
        print_r($result);

    }

}