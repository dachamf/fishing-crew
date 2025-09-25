<?php
// app/Enums/ConfirmationStatus.php
namespace App\Enums;

enum ConfirmationStatus: string {
    case PENDING  = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
