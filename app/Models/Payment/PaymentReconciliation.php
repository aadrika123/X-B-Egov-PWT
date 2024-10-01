<?php

namespace App\Models\Payment;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentReconciliation extends Model
{
    use HasFactory;
    protected $connection = 'pgsql_master';

    /**
     * | --------------------------- All Details of Payment Reconciliation ------------------------------- |
     * | Opreration : fetching all the details of the payment Reconcillation for the table 
     * | Rating : 1
     */

    public function allReconciliationDetails()
    {
        return PaymentReconciliation::select(
            'id',
            'ulb_id AS ulbId',
            'department_id AS dpartmentId',
            'transaction_no AS transactionNo',
            'payment_mode AS paymentMode',
            'date AS transactionDate',
            'status',
            'cheque_no AS chequeNo',
            'cheque_date AS chequeDate',
            'branch_name AS branchName',
            'bank_name AS bankName',
            'transaction_amount AS amount',
            'clearance_date AS clearanceDate'
        )->orderByDesc('id');
    }

    /**
     * 
     */
    public function addReconcilation($req)
    {
        $mPaymentReconciliation = new PaymentReconciliation();
        $mPaymentReconciliation->cheque_dtl_id = $req->id;
        $mPaymentReconciliation->payment_mode = $req->paymentMode;
        $mPaymentReconciliation->transaction_no = $req->transactionNo;
        $mPaymentReconciliation->transaction_amount = $req->transactionAmount;
        $mPaymentReconciliation->transaction_date = $req->transactionDate;
        $mPaymentReconciliation->bounce_reason = strtoupper($req->remarks);
        $mPaymentReconciliation->status = strtoupper($req->status);
        $mPaymentReconciliation->date = Carbon::now();
        // $mPaymentReconciliation->department_id = $req->departmentId;
        $mPaymentReconciliation->ulb_id = $req->ulbId;
        $mPaymentReconciliation->user_id = $req->userId;
        $mPaymentReconciliation->ward_mstr_id = $req->wardId ?? null;
        $mPaymentReconciliation->cheque_no = $req->chequeNo;
        $mPaymentReconciliation->branch_name = strtoupper($req->branchName);
        $mPaymentReconciliation->bank_name = strtoupper($req->bankName);
        $mPaymentReconciliation->clearance_date = $req->clearanceDate;
        $mPaymentReconciliation->cheque_date = $req->chequeDate;
        $mPaymentReconciliation->cancellation_charge = $req->cancellationCharge;
        $mPaymentReconciliation->module_id = $req->moduleId;
        $mPaymentReconciliation->save();
    }
}
