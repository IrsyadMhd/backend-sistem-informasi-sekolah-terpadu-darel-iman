<?php

namespace App\Http\Controllers\Api\Approval;

use App\Http\Controllers\Controller;
use App\Models\DeleteRequest;
use App\Services\Approval\DeleteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteRequestController extends Controller
{
    public function __construct(private readonly DeleteRequestService $deleteRequestService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DeleteRequest::query()->with(['requester', 'reviewer', 'educationUnit']);

        // If not Superadmin, limit to user's own submitted requests
        if (! $user->hasRole('Super Admin') && ! $user->hasPermissionTo('superadmin.delete.approve')) {
            $query->where('requested_by', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('target_table')) {
            $query->where('target_table', $request->input('target_table'));
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $requests,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'target_table' => 'required|string',
            'target_id' => 'required|uuid',
            'target_label' => 'nullable|string',
            'reason' => 'required|string|min:5',
            'attachment_path' => 'nullable|string',
            'education_unit_id' => 'nullable|uuid',
        ]);

        $deleteRequest = $this->deleteRequestService->createDeleteRequest(
            requester: $request->user(),
            targetTable: $request->input('target_table'),
            targetId: $request->input('target_id'),
            targetLabel: $request->input('target_label'),
            reason: $request->input('reason'),
            attachmentPath: $request->input('attachment_path'),
            educationUnitId: $request->input('education_unit_id')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permintaan penghapusan berhasil diajukan dan menunggu persetujuan Superadmin.',
            'data' => $deleteRequest->load('requester'),
        ], 201);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $deleteRequest = $this->deleteRequestService->approveDeleteRequest(
            approver: $request->user(),
            requestId: $id
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permintaan penghapusan telah disetujui. Data telah terhapus dari sistem.',
            'data' => $deleteRequest,
        ]);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:3',
        ]);

        $deleteRequest = $this->deleteRequestService->rejectDeleteRequest(
            reviewer: $request->user(),
            requestId: $id,
            rejectionReason: $request->input('rejection_reason')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Permintaan penghapusan telah ditolak. Data tetap aktif.',
            'data' => $deleteRequest,
        ]);
    }
}
