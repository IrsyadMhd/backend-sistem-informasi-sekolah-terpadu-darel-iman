<?php

namespace App\Services\Approval;

use App\Models\DeleteRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteRequestService
{
    public function createDeleteRequest(
        User $requester,
        string $targetTable,
        string $targetId,
        ?string $targetLabel = null,
        string $reason = '',
        ?string $attachmentPath = null,
        ?string $educationUnitId = null
    ): DeleteRequest {
        if (! Schema::hasTable($targetTable)) {
            throw new NotFoundHttpException("Tabel target '{$targetTable}' tidak ditemukan.");
        }

        $exists = DB::table($targetTable)->where('id', $targetId)->whereNull('deleted_at')->exists();
        if (! $exists) {
            // Check if table has soft delete or not
            $exists = DB::table($targetTable)->where('id', $targetId)->exists();
        }

        if (! $exists) {
            throw new NotFoundHttpException("Data target dengan ID '{$targetId}' pada tabel '{$targetTable}' tidak ditemukan.");
        }

        return DeleteRequest::create([
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'target_label' => $targetLabel ?? "Data {$targetTable} #{$targetId}",
            'requested_by' => $requester->id,
            'education_unit_id' => $educationUnitId,
            'reason' => $reason,
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
        ]);
    }

    public function approveDeleteRequest(User $approver, string $requestId): DeleteRequest
    {
        if (! $approver->hasRole('Super Admin') && ! $approver->hasPermissionTo('superadmin.delete.approve')) {
            throw new AccessDeniedHttpException('Hanya Superadmin yang memiliki wewenang untuk menyetujui penghapusan data.');
        }

        $deleteRequest = DeleteRequest::query()->where('id', $requestId)->first();
        if (! $deleteRequest) {
            throw new NotFoundHttpException('Permintaan penghapusan tidak ditemukan.');
        }

        if ($deleteRequest->status !== 'pending') {
            throw new \InvalidArgumentException("Permintaan penghapusan ini sudah diproses sebelumnya (Status: {$deleteRequest->status}).");
        }

        DB::transaction(function () use ($deleteRequest, $approver) {
            $table = $deleteRequest->target_table;
            $id = $deleteRequest->target_id;

            if (Schema::hasColumn($table, 'deleted_at')) {
                DB::table($table)->where('id', $id)->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table($table)->where('id', $id)->delete();
            }

            $deleteRequest->update([
                'status' => 'approved',
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
            ]);
        });

        return $deleteRequest->fresh(['requester', 'reviewer']);
    }

    public function rejectDeleteRequest(User $reviewer, string $requestId, string $rejectionReason): DeleteRequest
    {
        if (! $reviewer->hasRole('Super Admin') && ! $reviewer->hasPermissionTo('superadmin.delete.reject')) {
            throw new AccessDeniedHttpException('Hanya Superadmin yang memiliki wewenang untuk menolak permintaan penghapusan data.');
        }

        $deleteRequest = DeleteRequest::query()->where('id', $requestId)->first();
        if (! $deleteRequest) {
            throw new NotFoundHttpException('Permintaan penghapusan tidak ditemukan.');
        }

        if ($deleteRequest->status !== 'pending') {
            throw new \InvalidArgumentException("Permintaan penghapusan ini sudah diproses sebelumnya (Status: {$deleteRequest->status}).");
        }

        $deleteRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $rejectionReason,
        ]);

        return $deleteRequest->fresh(['requester', 'reviewer']);
    }
}
