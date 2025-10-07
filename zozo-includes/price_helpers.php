<?php
// Helper to format euro prices without the € sign.
// Rules:
// - if cents are 0: show "7,-"
// - otherwise: show "6,85" (comma as decimal separator, two decimals)
// Note: callers may want to special-case 'op maat' or other strings when price==0.
function format_price_eur_nosymbol($amount)
{
    $amount = (float) $amount;
    $amount = round($amount, 2);

    // If exactly zero, return 0,- (callers can override to 'op maat')
    if ($amount == 0.0) {
        return '0,-';
    }

    // Separate euros and cents
    $euros = floor($amount);
    $cents = (int) round(($amount - $euros) * 100);

    if ($cents === 0) {
        return $euros . ',-';
    }

    // Keep two decimals with comma as decimal separator
    return number_format($amount, 2, ',', '.');
}
