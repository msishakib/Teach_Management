<?php

namespace App\Enums;

enum NumberSequenceType: string
{
    case Admission = 'admission';
    case Employee = 'employee';
    case Invoice = 'invoice';
    case FeeReceipt = 'fee_receipt';
    case PaymentVoucher = 'payment_voucher';
    case ReceiveVoucher = 'receive_voucher';
    case Certificate = 'certificate';

    public function prefixSettingKey(): string
    {
        return "numbering.{$this->value}_prefix";
    }

    public function sequencesSettingKey(): string
    {
        return "numbering.{$this->value}_yearly_sequences";
    }

    public function settingGroup(): string
    {
        return 'numbering';
    }

    public function sequencesSettingName(): string
    {
        return "{$this->value}_yearly_sequences";
    }

    public function prefixSettingName(): string
    {
        return "{$this->value}_prefix";
    }
}
