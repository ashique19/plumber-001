<?php

namespace App\Enums;

enum LoyaltyTransactionType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Referral = 'referral';
    case Expire = 'expire';
    case Adjust = 'adjust';
}
