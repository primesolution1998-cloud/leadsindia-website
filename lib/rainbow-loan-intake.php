<?php
declare(strict_types=1);

function rainbow_loan_emi(float $principal, float $annualPercent, int $months): array
{
    if (!is_finite($principal) || !is_finite($annualPercent) || $principal <= 0 || $principal > 1000000000 || $annualPercent < 0 || $annualPercent > 100 || $months < 1 || $months > 600) {
        throw new InvalidArgumentException('invalid_emi_inputs');
    }
    $monthlyRate = $annualPercent / 1200;
    // expm1/log1p avoid cancellation at very small nonzero rates.
    $emi = $monthlyRate == 0.0 ? $principal / $months : $principal * $monthlyRate / -expm1(-$months * log1p($monthlyRate));
    $total = $emi * $months;
    return ['emi'=>round($emi, 2), 'total_repayment'=>round($total, 2), 'total_interest'=>round($total - $principal, 2)];
}

/** Handles only explicit commands, without inferring financial inputs from prose. */
function rainbow_loan_local_answer(string $agent, string $task): ?string
{
    if (!isset(rainbow_loan_specialists()[$agent])) return null;
    $query = trim($task);
    $query = preg_replace('/^Assan\s?Loan\s+project[.:]\s*/i', '', $query) ?? $query;
    if (preg_match('/^personal\s+loan[.!?\s]*$/i', $query)) {
        return "Personal loan ke liye pehle ye details batayein:\n\n".
            "1. Kitna loan chahiye aur kis purpose ke liye?\n".
            "2. Salaried ya self-employed? Monthly take-home income kitni hai?\n".
            "3. Existing monthly EMI kitni hai?\n".
            "4. City/state aur approximate CIBIL score, agar pata ho.\n".
            "5. Preferred repayment period?\n\n".
            "Isse indicative affordability aur document checklist banegi; final approval lender karega. OTP, full PAN/Aadhaar ya bank account details yahan share na karein.\n\n".
            "Kisi bank ki policy chahiye to uska naam likhein, jaise: SBI personal loan.";
    }
    if (preg_match('/^emi\s+([0-9]+(?:\.[0-9]+)?)\s+([0-9]+(?:\.[0-9]+)?)\s+([0-9]+)[.!?\s]*$/i', $query, $m)) {
        try {
            $result = rainbow_loan_emi((float)$m[1], (float)$m[2], (int)$m[3]);
        } catch (InvalidArgumentException $e) {
            return 'EMI inputs invalid. Principal INR 0 se zyada aur 100 crore tak, annual rate 0–100%, tenure 1–600 months dein. Format: EMI 500000 14 36';
        }
        return "Illustrative EMI calculation\n\n".
            'Principal: INR '.number_format((float)$m[1], 2)."\n".
            'Assumed annual rate: '.$m[2]."% (lender offer verified nahi hai)\n".
            'Tenure: '.(int)$m[3]." months\n".
            'Monthly EMI: INR '.number_format($result['emi'], 2)."\n".
            'Total repayment: INR '.number_format($result['total_repayment'], 2)."\n".
            'Total interest: INR '.number_format($result['total_interest'], 2)."\n\n".
            'Reducing-balance calculation; total unrounded EMI se calculate hua hai. Fees, insurance aur taxes included nahi hain; lender rounding se small difference ho sakta hai.';
    }
    return null;
}
