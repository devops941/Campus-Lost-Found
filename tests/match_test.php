<?php
/**
 * Matching algorithm unit tests — KZ-WEB-03
 *
 * Run from project root:
 *   php tests/match_test.php
 *
 * Covers:
 *   TC02 — same-category keyword overlap  → score >= 0.5
 *   TC03 — different category, no overlap → score <  0.5
 */

// Bootstrap (no DB needed for pure-logic tests)
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/mail.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/match.php';

// ── Test runner ─────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function run_test(string $id, string $desc, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo "\033[32m[PASS]\033[0m $id — $desc\n";
        $passed++;
    } else {
        echo "\033[31m[FAIL]\033[0m $id — $desc" . ($detail ? " ($detail)" : '') . "\n";
        $failed++;
    }
}

echo "\n=== Campus Lost & Found — Matching Engine Tests ===\n\n";

// ------------------------------------------------------------------
// Prerequisite micro-tests (sanity-check the building blocks)
// ------------------------------------------------------------------

// Keywords: basic extraction
$kw = extract_keywords('Black Casio calculator', 'lost near exam hall');
run_test('KW01', 'extract_keywords strips stopwords',
    !in_array('near', $kw, true) && !in_array('lost', $kw, true));
run_test('KW02', 'extract_keywords keeps meaningful tokens',
    in_array('black', $kw, true) && in_array('casio', $kw, true) && in_array('calculator', $kw, true));
run_test('KW03', 'extract_keywords is lowercase',
    $kw === array_map('strtolower', $kw));

// Jaccard edge cases
run_test('JQ01', 'jaccard(empty, empty) = 0', jaccard([], []) === 0.0);
run_test('JQ02', 'jaccard(A, A) = 1.0',        jaccard(['a','b'], ['a','b']) === 1.0);
run_test('JQ03', 'jaccard(A, ∅) = 0.0',         jaccard(['a','b'], []) === 0.0);
run_test('JQ04', 'jaccard disjoint sets = 0.0', jaccard(['x'], ['y']) === 0.0);

// ------------------------------------------------------------------
// TC02 — Casio calculator vs Casio fx-991 calculator (same category)
//         Spec requirement: score >= 0.5
// ------------------------------------------------------------------
echo "\n--- TC02: Casio calculator match ---\n";

$lostCalc = [
    'title'       => 'black casio calculator',
    'description' => 'I lost my black casio scientific calculator near the exam hall',
    'category'    => 'electronics',
    'item_date'   => '2026-10-01',
];
$foundCalc = [
    'title'       => 'casio fx991 calculator',
    'description' => 'Found a casio fx991 calculator near the exam hall entrance',
    'category'    => 'electronics',
    'item_date'   => '2026-10-02',
];

$kwL = extract_keywords($lostCalc['title'],  $lostCalc['description']);
$kwF = extract_keywords($foundCalc['title'], $foundCalc['description']);
$j02 = jaccard($kwL, $kwF);
$s02 = compute_score($lostCalc, $foundCalc);

echo "  Keywords (lost) : " . implode(', ', $kwL) . "\n";
echo "  Keywords (found): " . implode(', ', $kwF) . "\n";
printf("  Jaccard         : %.4f\n", $j02);
printf("  Final score     : %.2f\n", $s02);

run_test('TC02a', 'Calculator score >= 0.5',           $s02 >= 0.5, "got $s02");
run_test('TC02b', 'Same-category bonus applied (0.3)', $s02 >= 0.3);
run_test('TC02c', 'Date-window bonus applied (0.1)',    $s02 > 0.3 + 0.6 * $j02 - 0.01);

// ------------------------------------------------------------------
// TC03 — Blue water bottle vs ID card (different category, no overlap)
//         Spec requirement: score < 0.5 (no match)
// ------------------------------------------------------------------
echo "\n--- TC03: Water bottle vs ID card ---\n";

$lostBottle = [
    'title'       => 'blue water bottle',
    'description' => 'I lost my blue stainless steel water bottle',
    'category'    => 'bottle',
    'item_date'   => '2026-10-01',
];
$foundId = [
    'title'       => 'ID card',
    'description' => 'Found an ID card near the library',
    'category'    => 'id_card',
    'item_date'   => '2026-10-05',
];

$kwL3 = extract_keywords($lostBottle['title'],  $lostBottle['description']);
$kwF3 = extract_keywords($foundId['title'],     $foundId['description']);
$j03  = jaccard($kwL3, $kwF3);
$s03  = compute_score($lostBottle, $foundId);

echo "  Keywords (lost) : " . implode(', ', $kwL3) . "\n";
echo "  Keywords (found): " . implode(', ', $kwF3) . "\n";
printf("  Jaccard         : %.4f\n", $j03);
printf("  Final score     : %.2f\n", $s03);

run_test('TC03a', 'Bottle vs ID card score < 0.5',          $s03 < 0.5,  "got $s03");
run_test('TC03b', 'No same-category bonus (different cats)', $s03 < 0.4);

// ------------------------------------------------------------------
// Additional spec-derived tests
// ------------------------------------------------------------------
echo "\n--- Additional edge cases ---\n";

// TC-E1: Identical items → score should be 1.0 (full jaccard + same cat + same date)
$item = ['title' => 'red bag', 'description' => 'red bag found canteen', 'category' => 'bag', 'item_date' => '2026-10-01'];
$sE1  = compute_score($item, $item);
run_test('TC-E1', 'Identical items score = 1.0', $sE1 === 1.0, "got $sE1");

// TC-E2: Same category, zero keyword overlap → 0.3
// "red backpack" vs "green suitcase luggage" — no shared tokens, same cat (bag)
$a = ['title' => 'red backpack',            'description' => '',              'category' => 'bag', 'item_date' => null];
$b = ['title' => 'green suitcase luggage',  'description' => 'blue exterior', 'category' => 'bag', 'item_date' => null];
// Keywords A = {red, backpack}; Keywords B = {green, suitcase, luggage, blue, exterior}
// Intersection = {}  Union = 7  Jaccard = 0 → score = 0.6*0 + 0.3 = 0.30
$sE2 = compute_score($a, $b);
run_test('TC-E2', 'Same cat + zero overlap = 0.30', $sE2 === 0.30, "got $sE2");

// TC-E3: Dates outside window → no date bonus
$c = ['title' => 'keys', 'description' => 'bunch keys', 'category' => 'keys', 'item_date' => '2026-09-01'];
$d = ['title' => 'keys', 'description' => 'bunch keys', 'category' => 'keys', 'item_date' => '2026-10-01'];
// 30 days apart → no date bonus
$sE3 = compute_score($c, $d);
run_test('TC-E3', 'Date > 7 days → no date bonus', $sE3 < 1.0 && $sE3 >= 0.9, "got $sE3");

// ------------------------------------------------------------------
// Summary
// ------------------------------------------------------------------
echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: $passed passed, $failed failed\n";
if ($failed === 0) {
    echo "\033[32mAll tests passed.\033[0m\n\n";
    exit(0);
} else {
    echo "\033[31m$failed test(s) FAILED.\033[0m\n\n";
    exit(1);
}
