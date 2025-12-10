<?php

namespace App\Services;

use Spatie\PdfToText\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Gemini\Laravel\Facades\Gemini;

class ResumeAnalysisService
{
    public function extractResumeInformation(string $fileUrl)
    {
        try {
            $rawtext = $this->extractTextFromPdf($fileUrl);

            Log::debug('Extracted Text: ' . strlen($rawtext) . ' characters');

            // Use Gemini API to organize the text into structured format
            $prompt = "You are a precise resume parser. Extract information exactly as it appears in the resume without adding any interpretation or additional information.
            Parse the following resume content and extract the information as a JSON Object with the exact keys: 'summary', 'skills', 'experience', 'education'.
            The resume content is: {$rawtext}.
            Return an empty string for any key that is not found.
            Ensure the output is valid JSON.";

            $result = Gemini::generativeModel(model: 'gemini-2.5-flash-lite')
                ->generateContent($prompt)
                ->text();

            // Clean up the response if it contains markdown code blocks
            $result = str_replace(['```json', '```'], '', $result);

            Log::debug('Gemini response: ' . $result);

            $parsedResult = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Gemini response: ' . json_last_error_msg());
                throw new \Exception('Failed to parse Gemini response');
            }

            // Validate the parsed result
            $requiredKeys = ['summary', 'skills', 'experience', 'education'];
            $missingKeys = array_diff($requiredKeys, array_keys($parsedResult));

            if (count($missingKeys) > 0) {
                Log::error('Missing required keys: ' . implode(', ', $missingKeys));
                // Instead of throwing, we can fill missing keys with empty strings to be safe
                foreach ($missingKeys as $key) {
                    $parsedResult[$key] = '';
                }
            }

            Log::info('Parsed result: ' . json_encode($parsedResult));

            // Return the JSON object
            return [
                'summary' => $parsedResult['summary'] ?? '',
                'skills' => $parsedResult['skills'] ?? '',
                'experience' => $parsedResult['experience'] ?? '',
                'education' => $parsedResult['education'] ?? ''
            ];

        } catch (\Exception $e) {
            Log::error('Error extracting resume information: ' . $e->getMessage());
            return [
                'summary' => '',
                'skills' => '',
                'experience' => '',
                'education' => ''
            ];
        }
    }

    private function extractTextFromPdf(string $fileUrl): string
    {
        // Reading the file from the cloud to local disk storage in
        $tempFile = tempnam(sys_get_temp_dir(), 'resume_');

        $filePath = parse_url($fileUrl, PHP_URL_PATH);
        if (!$filePath) {
            throw new \Exception('Invalid file URL');
        }

        $filename = basename($filePath);

        $storagePath = "resumes/{$filename}";

        if (!Storage::disk('cloud')->exists($storagePath)) {
            throw new \Exception('File not found');
        }

        $pdfContent = Storage::disk('cloud')->get($storagePath);
        if (!$pdfContent) {
            throw new \Exception('Failed to read file');
        }

        file_put_contents($tempFile, $pdfContent);

        // Check if pdf-to-text is installed
        $pdfToTextAvailable = false;
        $pdfToTextPath = 'C:\\Program Files\\poppler\\Library\\bin\\pdftotext.exe';
        if (file_exists($pdfToTextPath)) {
            $pdfToTextAvailable = true;
        }
        if (!$pdfToTextAvailable) {
            throw new \Exception('pdf-to-text utility is not installed');
        }

        //extract text using pdftotext
        $instance = new Pdf($pdfToTextPath);
        $instance->setPdf($tempFile);
        $text = $instance->text();
        // Clean up temporary file
        unlink($tempFile);
        return $text;
    }

    public function analyzeResume($jobVacancy, $resumeData)
    {
        try {
            $jobDetails = json_encode([
                'job_title' => $jobVacancy->title,
                'job_description' => $jobVacancy->description,
                'job_location' => $jobVacancy->location,
                'job_type' => $jobVacancy->type,
                'job_salary' => $jobVacancy->salary,
            ]);

            $resumeDetails = json_encode($resumeData);

            $prompt = "You are an expert HR professional and job recruiter. You are given a job vacancy and a resume.
            Your task is to analyze the resume and determine if the candidate is a good fit for the job.
            The output should be in JSON format.
            Provide a score from 0 to 100 for the candidate's suitability for the job, and a detailed feedback.
            Response should only be Json that has the following keys: 'aiGeneratedScore', 'aiGeneratedFeedback'.
            Aigenerate feedback should be detailed and specific to the job and the candidate's resume.
            
            Job Details: {$jobDetails}. 
            Resume Details: {$resumeDetails}";

            $result = Gemini::generativeModel(model: 'gemini-2.5-flash-lite')
                ->generateContent($prompt)
                ->text();

            // Clean up the response if it contains markdown code blocks
            $result = str_replace(['```json', '```'], '', $result);

            Log::debug('Gemini evaluation response: ' . $result);

            $parsedResult = json_decode($result, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Gemini response: ' . json_last_error_msg());
                throw new \Exception('Failed to parse Gemini response');
            }

            if (!isset($parsedResult['aiGeneratedScore']) || !isset($parsedResult['aiGeneratedFeedback'])) {
                Log::error('Missing required keys in the parsed result');
                throw new \Exception('Missing required keys in the parsed result');
            }

            return $parsedResult;

        } catch (\Exception $e) {
            Log::error('Error analyzing resume: ' . $e->getMessage());
            return [
                'aiGeneratedScore' => 0,
                'aiGeneratedFeedback' => ''
            ];
        }
    }
}