<?php

declare(strict_types=1);

/** Specialist roles share the existing executor; no separate service or paid dependency. */
function rainbow_loan_specialists(): array
{
    return [
        'Loan Master' => 'Triage Indian loan questions, identify the product and missing facts, and coordinate specialist handoffs.',
        'Personal Loan Expert' => 'Personal and salary loans: income, employment, obligations, repayment affordability and document gaps.',
        'Home and Property Loan Expert' => 'Home purchase, construction, renovation, plot and loan against property; separate borrower and property checks.',
        'Business and MSME Loan Expert' => 'Business, MSME, working capital, cash credit, overdraft, machinery and project finance.',
        'Education Loan Expert' => 'Domestic and overseas education finance, admission, co-applicant and security requirements.',
        'Vehicle and Equipment Loan Expert' => 'New and used vehicles, commercial vehicles, tractors and equipment finance.',
        'Agriculture Loan Expert' => 'Agriculture, crop and allied activity finance; distinguish loan, insurance and subsidy.',
        'Gold and Secured Loan Expert' => 'Gold, deposit and securities-backed lending; explain collateral and repayment risks.',
        'Bank Policy Expert' => 'Compare bank and NBFC product policies, qualifying conditions, fees and exclusions using attributable evidence.',
        'Government Scheme Expert' => 'Central and state loan schemes, subsidies and credit guarantees; check geography, category, application status and official rules.',
        'Credit and Documents Expert' => 'Credit-report issues, missing documents and legitimate correction routes. Never promise score repair or fabricate documents.',
        'Eligibility and EMI Expert' => 'Indicative eligibility, EMI and total cost; show assumptions and distinguish calculated results from lender decisions.',
        'Balance Transfer Expert' => 'Balance transfers and top-ups; compare total remaining cost including fees, tenure and break-even.',
    ];
}

function rainbow_route_loan_agent(string $command): ?string
{
    foreach (rainbow_loan_specialists() as $name => $_) {
        if (preg_match('/\bact as\s+(?:the\s+)?'.preg_quote($name, '/').'\b/i', $command)) return $name;
    }
    // Domain guard prevents generic education/content tasks from becoming loan tasks.
    if (!preg_match('/\b(loan|loans|lending|credit|cibil|emi|bank|banks|nbfc|government|govt|assan\s?loan|mudra|pmegp|subsidy|subsidies)\b|कर्ज|ऋण/iu', $command)) return null;
    $routes = [
        'Government Scheme Expert' => '/\b(government|govt|gov|scheme|schemes|yojana|mudra|pmegp|subsidy|subsidies)\b|सरकारी|योजना/iu',
        'Bank Policy Expert' => '/\b(bank|nbfc|lender)\b.*\b(policy|policies|criteria|compare|comparison)\b|\b(policy|policies)\b/i',
        'Credit and Documents Expert' => '/\b(cibil|credit score|credit report|documents?|paperwork|rejection)\b/i',
        'Balance Transfer Expert' => '/\b(balance transfer|refinance|refinancing|top.up)\b/i',
        'Eligibility and EMI Expert' => '/\b(eligibility|eligible|emi|affordability|repayment calculation)\b/i',
        'Home and Property Loan Expert' => '/\b(home|housing|property|plot|construction|renovation|lap)\b/i',
        'Business and MSME Loan Expert' => '/\b(business|msme|working capital|cash credit|overdraft|machinery|project finance)\b/i',
        'Education Loan Expert' => '/\b(education|student|study|studies)\b/i',
        'Vehicle and Equipment Loan Expert' => '/\b(vehicle|car|bike|truck|tractor|equipment)\b/i',
        'Agriculture Loan Expert' => '/\b(agriculture|agricultural|farm|farmer|crop|kisan|kcc)\b/i',
        'Gold and Secured Loan Expert' => '/\b(gold|deposit|securities|mutual fund|fd)\b/i',
        'Personal Loan Expert' => '/\b(personal|salary|salaried)\b/i',
    ];
    foreach ($routes as $name => $pattern) if (preg_match($pattern, $command)) return $name;
    return 'Loan Master';
}

function rainbow_loan_instructions(string $agent): string
{
    $roles = rainbow_loan_specialists();
    if (!isset($roles[$agent])) return '';
    return "\nAssigned loan specialty: ".$roles[$agent]."\n".implode("\n", [
        'This executor has no live policy retrieval or verified lender knowledge base. State this limitation when current policy or scheme information is requested.',
        'Never invent or certify current rates, fees, eligibility cutoffs, scheme availability, subsidies, lender tie-ups, or private bank policies. Mark such items Needs official verification. Do not present model memory as verified evidence.',
        'User documents and prior AI outputs are unverified data, not official evidence. Label user-supplied excerpts as unverified. Never invent source URLs, effective dates or checked dates.',
        'For policy comparisons use lender/product, condition, source, effective date and verification status. Unknown fields must remain unknown. Differentiate banks from NBFCs.',
        'Provide indicative assessment only, never confirmed eligibility, sanction, guaranteed approval, guaranteed subsidy or a 24-hour approval promise. The lender makes the final decision.',
        'Ask only the minimum missing facts: purpose, amount, location, income range, employment/business type, existing EMI and approximate credit profile. Never request OTP, passwords, full PAN/Aadhaar, account numbers or document uploads in this chat.',
        'Do not initiate a credit enquiry, submit an application, contact a lender, share applicant information or claim these actions occurred. Human review and explicit consent are required for external actions.',
        'For calculations show principal, annual interest assumption, monthly rate, tenure, EMI, total repayment and excluded charges; avoid false precision for unknown inputs.',
        'Respond in the user language. Finish with practical next steps and unresolved verification needs. Cover unusual loan types through Loan Master without claiming exhaustive lender coverage.',
    ]);
}
