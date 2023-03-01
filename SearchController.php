<?php

namespace App\Http\Controllers;

use App\Http\Requests\Schema\PatientRequest;
use App\Http\Requests\SearchRequest;
use App\Http\Resources\Erx\SchemaSearchResource;
use App\Http\Resources\Erx\SearchPaginateResource;
use App\Http\Resources\Erx\SearchResource;
use App\Http\Resources\Pattern\ProtocolSearchPaginateResource;
use App\Http\Resources\Schema\MagicPaginateResource;
use App\Models\Erx\ErxItem;
use App\Models\Identity\User;
use App\Services\Prescription\PrescriptionService;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    /**
     * search magic
     *
     * @OA\Get(
     *     path="/api/search/magic",
     *     tags={"Search"},
     *     summary="search magic items",
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="text", description="text to search", example="PIPERACILLIN", @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="national_code", description="patient national code", example="0010925831", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="type", description="T , I", example="PIPERACILLIN", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Parameter(name="protocol", description="(D, other)", example="D", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Drug"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param Request $request
     * @param SearchService $searchService
     * @return JsonResponse [string] message
     */
    public function magicSearch(SearchRequest $request, SearchService $searchService)
    {

        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $type = strtoupper($request->input('type')) == 'T';
        $text = $request->input('text');
        $medical_no = $user->employee->medical_no;
        $specialty_id = $user->employee->specialty_id;
        $last = Str::substr($text, -1, 1);
        $key = Str::substr($text, 0, 1);
        $start = 1;
        if (in_array($last, ['+', '.', '/', '*'])) {
            $key = $last;
            $start = 0;
        }
        $word = Str::substr($text, $start, Str::length($text) - 1);
        $headerText = $word;
        $limit = (int)$request->input('limit');
        $protocol = $request->input('protocol');

        switch ($key) {
            case '.':
                $code = $request->input('national_code');
                break;
            case '*':
                $code = $medical_no;
                break;
            case '/':
                $code = $user->employee->speciality->id;
                break;
            case '+':
                $code = '';
                break;
            default:
                $code = '';
                $headerText = $text;
        }

        $items = collect([]);
        if ($text !== '') {
            $items = $searchService->magic($key, $word, $code, $limit, $type);
            if ($items === false) {
                return $this->sendError("خطای جستجو", [
                    'error' => 'Server error',
                    'class' => __CLASS__,
                    'line' => __LINE__,
                ], 500);
            }
        }
        if ($protocol == 'D' || $protocol == 'S') {
            //            $headers = $searchService->searchPattern($headerText, $limit);
            $doctor_headers = $searchService->searchDoctorPattern($medical_no, $headerText, $limit);
            if ($doctor_headers === false) {
                return $this->sendError("خطای جستجو", [
                    'error' => 'Server error',
                    'class' => __CLASS__,
                    'line' => __LINE__,
                ], 500);
            }
        }
        if ($protocol == 'S') {
            $specialty_headers = $searchService->searchSpecialtyPattern($specialty_id, $headerText, $limit);
            if ($specialty_headers === false) {
                return $this->sendError("خطای جستجو", [
                    'error' => 'Server error',
                    'class' => __CLASS__,
                    'line' => __LINE__,
                ], 500);
            }
        }
        $headers = $searchService->searchPattern($headerText, $limit);
        if ($headers === false) {
            return $this->sendError("خطای جستجو", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }

        $resource = ($code == '') ? new SearchPaginateResource($items) : new MagicPaginateResource($items);
        if ($protocol == 'D' && isset($doctor_headers)) {
            $header = $doctor_headers->concat($headers);
        } else if ($protocol == 'S' && isset($specialty_headers) && isset($doctor_headers)) {
            $header = $specialty_headers->concat($doctor_headers);
        } else {
            $header = $headers;
        }

        $unique_headers = $header->unique();
        $header_resource = new ProtocolSearchPaginateResource($unique_headers);
        $success = [
            'items' => $resource,
            'protocols' => $header_resource
        ];

        return $this->sendResponse($success, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/search/doctor/magic",
     *     tags={"Search"},
     *     summary="magic search for doctor",
     *     description="magic search for doctor favorite items",
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Prescription"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Prescription not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @return JsonResponse [string] message
     */
    public function magicDoctor(Request $request, SearchService $searchService)
    {

        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $medical_no = $user->employee->medical_no;
        $specialty_id = $user->employee->specialty_id;
        $limit = $request->input('limit') ?? 100;
        $doctor_items = $searchService->getDoctorFavorite($medical_no, $limit);
        if ($doctor_items === false) {
            return $this->sendError("خطای جستجو", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $specialty_items = $searchService->getSpecialtyFavorite($specialty_id, $limit);
        if ($specialty_items === false) {
            return $this->sendError("خطای جستجو", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $item_resource = [
            'doctor_items' =>SchemaSearchResource::collection($doctor_items),
            'specialty_items' =>SchemaSearchResource::collection($specialty_items)
        ];
        return $this->sendResponse($item_resource, 'success.');
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/api/search/patient/magic",
     *     tags={"Search"},
     *     summary="magic search for patient",
     *     description="magic search for patient repetitive items",
     *     @OA\Parameter(name="limit", description="limit per page", example="10", required=false, @OA\Schema(type="integer"), in="query"),
     *     @OA\Parameter(name="national_code", description="patient national code", example="0010925831", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/Prescription"),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid ID"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Prescription not found"
     *     ),
     *     security={
     *       {"passport": {}},
     *     },
     * )
     *
     * @param Request $request
     * @param $id
     * @param PrescriptionService $prescriptionService
     * @return JsonResponse [string] message
     */
    public function magicPatient(PatientRequest $request, SearchService $searchService)
    {

        /* @var User $user */
        $user = auth('api')->user();
        //permission in Service
        $national_code = $request->input('national_code');
        $limit = $request->input('limit') ?? 100;
        $patient_items = $searchService->getPatientRepeatitive($national_code, $limit);
        if ($patient_items === false) {
            return $this->sendError("خطای جستجو", [
                'error' => 'Server error',
                'class' => __CLASS__,
                'line' => __LINE__,
            ], 500);
        }
        $item_resource = [
            'items' =>SchemaSearchResource::collection($patient_items),
        ];
        return $this->sendResponse($item_resource, 'success.');
    }

    /**
     * search pattern
     *
     * @OA\Get(
     *     path="/api/search/pattern",
     *     tags={"Search"},
     *     summary="search pattern items",
     *     @OA\Parameter(name="code", description="drug's standard code", example="149", required=true, @OA\Schema(type="string"), in="query"),
     *     @OA\Response(
     *         response="200",
     *         description="successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Drug"),
     *         ),
     *     ),
     *       security={
     *       {"passport": {}},
     *     },
     *
     * )
     *
     * @param Request $request
     * @param SearchService $searchService
     * @return JsonResponse [string] message
     */
    public function patternSearch(Request $request, SearchService $searchService)
    {

        /* @var User $user */
        $user = auth('api')->user();

        $res = collect(DB::select(
            "exec [dbo].[MagicSearchPatterns] @code = ?, @specialty = ?", array($request->query('code'), $user->employee->speciality->id)
        ))->unique('standard_code')->pluck('standard_code')->toArray();
        $items = ErxItem::query()->whereRaw('code = brand')->whereIn('code', $res)->get();

        $success = ['items' => new SearchPaginateResource($items)];

        return $this->sendResponse($success, 'success.');
    }
}
