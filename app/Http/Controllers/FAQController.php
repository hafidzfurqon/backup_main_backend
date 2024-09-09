<?php

namespace App\Http\Controllers;

use App\Models\FAQ;
use App\Services\CheckAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FAQController extends Controller
{
    protected $checkAdminService;

    // Inject RoleService ke dalam constructor
    public function __construct(CheckAdminService $checkAdminService)
    {
        $this->checkAdminService = $checkAdminService;
    }

    public function index(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'You do not have permission to fetch FAQs.'
            ], 403);
        }

        try {
            $faqs = FAQ::all();

            if ($faqs->isEmpty()) {
                return response()->json([
                    'message' => 'FAQ is empty'
                ], 404);
            }

            return response()->json([
                'data' => $faqs
            ], 200);

        } catch (\Exception $e) {
            Log::error('An error occurred while fetching FAQs: ' . $e->getMessage());
            
            return response()->json([
                'errors' => 'An error occurred while fetching FAQs.'
            ], 500);
        }
    }

    public function showSpesificFAQ($id)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'You do not have permission to fetch FAQs.'
            ], 403);
        }
        
        try {

            $faq = FAQ::find($id);
            if (!$faq) {
                return response()->json([
                    'errors' => 'FAQ not found'
                ], 404);
            }

            return response()->json([
                'data' => $faq
            ], 200);

        } catch(\Exception $e) {
            Log::error('Error occured while fetching spesific FAQ: ' . $e->getMessage());

            return response()->json([
                'errors' => 'An error occurred while fetching spesific FAQ.'
            ], 500);

        }
    }

    public function store(Request $request)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'You do not have permission to create FAQs.'
            ], 403);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'question' => 'required|string',
                'answer' => 'required|string',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {

            $faq = FAQ::create([
                'question' => $request->question,
                'answer' => $request->answer
            ]);

            DB::commit();

            return response()->json([
                'message' => 'FAQ created successfully',
                'data' => $faq
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('An error occurred while creating FAQ: ' . $e->getMessage());

            return response()->json([
                'errors' => 'An error occurred while creating FAQ.'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'You do not have permission to update FAQs.'
            ], 403);
        }

        $faq = FAQ::find($id);

        if(!$faq) {
            return response()->json([
                'errors' => 'FAQ not found'
            ], 404);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'question' => 'required|string',
                'answer' => 'required|string',
            ],
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $faq->question = $request->question;    
            $faq->answer = $request->answer;
            $faq->save();
            DB::commit();

            return response()->json([
                'message' => 'FAQ updated successfully',
                'data' => $faq
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('An error occurred while updating FAQ: ' . $e->getMessage());

            return response()->json([
                'errors' => 'An error occurred while updating FAQ.'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $checkAdmin = $this->checkAdminService->checkAdmin();

        if(!$checkAdmin) {
            return response()->json([
                'errors' => 'You do not have permission to delete FAQs.'
            ], 403);
        }

        $faq = FAQ::find($id);

        if(!$faq) {
            return response()->json([
                'errors' => 'FAQ not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $faq->delete();
            DB::commit();

            return response()->json([
                'message' => 'FAQ deleted successfully',
                'data' => $faq
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('An error occurred while deleting FAQ: ' . $e->getMessage());
            
            return response()->json([
                'errors' => 'An error occurred while deleting FAQ.'
            ], 500);
        }
    }
}
