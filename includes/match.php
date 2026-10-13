<?php
/**
 * Smart Matching Engine — KZ-WEB-03
 *
 * Public API:
 *   extract_keywords(title, description) → string[]
 *   jaccard(setA, setB)                 → float  0–1
 *   compute_score(lostItem, foundItem)  → float  0–1
 *   run_matching(newItemId)             → void   (saves to DB, emails users)
 */

// ── Tuning constants ────────────────────────────────────────────────
define('MATCH_THRESHOLD',   0.5);   // minimum score to save a match
define('MATCH_WINDOW_DAYS', 7);     // date proximity bonus window

// Words that add no signal to keyword matching
const STOPWORDS = [
    'a','an','the','is','my','near','of','in','on','at','and','i',
    'lost','found','it','to','was','with','have','has','from','for',
    'by','this','that','its','are','been','be','not','or','as','we',
    'they','he','she','his','her','our','some','one','two','please',
    'help','looking','anyone','saw','see','college','campus','left',
];

// ------------------------------------------------------------------
// 1. Keyword extraction
// ------------------------------------------------------------------

/**
 * Lower-case, strip punctuation, split on whitespace, remove stopwords.
 * Returns a unique set of meaningful tokens.
 *
 * @return string[]
 */
function extract_keywords(string $title, string $description): array
{
    $text  = strtolower($title . ' ' . $description);
    $text  = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    $words = array_filter(
        $words,
        static fn(string $w) => strlen($w) > 1 && !in_array($w, STOPWORDS, true)
    );
    return array_values(array_unique($words));
}

// ------------------------------------------------------------------
// 2. Jaccard similarity
// ------------------------------------------------------------------

/**
 * |A ∩ B| / |A ∪ B|
 * Returns 0.0 if both sets are empty.
 */
function jaccard(array $setA, array $setB): float
{
    if (empty($setA) && empty($setB)) {
        return 0.0;
    }
    $intersection = count(array_intersect($setA, $setB));
    $union        = count(array_unique(array_merge($setA, $setB)));
    return $union === 0 ? 0.0 : $intersection / $union;
}

// ------------------------------------------------------------------
// 3. Composite score
// ------------------------------------------------------------------

/**
 * score = 0.6 × jaccard(keywords)
 *       + 0.3  if same category
 *       + 0.1  if |item_date_A − item_date_B| ≤ MATCH_WINDOW_DAYS
 *
 * Result rounded to 2 decimal places.
 *
 * @param array $lostItem  Full item row (or test stub with title/description/category/item_date)
 * @param array $foundItem Full item row (same shape)
 */
function compute_score(array $lostItem, array $foundItem): float
{
    $kwLost  = extract_keywords($lostItem['title'],  $lostItem['description']  ?? '');
    $kwFound = extract_keywords($foundItem['title'], $foundItem['description'] ?? '');

    $score = 0.6 * jaccard($kwLost, $kwFound);

    if (($lostItem['category'] ?? null) === ($foundItem['category'] ?? null)) {
        $score += 0.3;
    }

    $dl = $lostItem['item_date']  ?? null;
    $df = $foundItem['item_date'] ?? null;
    if ($dl && $df) {
        $diffDays = abs(strtotime($dl) - strtotime($df)) / 86400;
        if ($diffDays <= MATCH_WINDOW_DAYS) {
            $score += 0.1;
        }
    }

    return round($score, 2);
}

// ------------------------------------------------------------------
// 4. Run matching for a newly posted item
// ------------------------------------------------------------------

/**
 * Compare newItemId against every OPEN item of the OPPOSITE type.
 * Works symmetrically for both new lost and new found posts.
 *
 * - Saves pairs with score ≥ MATCH_THRESHOLD using INSERT IGNORE.
 * - Sends email notifications; email failures are caught/logged silently.
 */
function run_matching(int $newItemId): void
{
    $pdo = get_db();

    $stmt = $pdo->prepare('SELECT * FROM items WHERE id = ? LIMIT 1');
    $stmt->execute([$newItemId]);
    $newItem = $stmt->fetch();

    if (!$newItem) {
        return;
    }

    $oppositeType = $newItem['type'] === 'lost' ? 'found' : 'lost';

    // Fetch all open items of the opposite type (any user, not just others)
    $stmt = $pdo->prepare(
        "SELECT * FROM items WHERE type = ? AND status = 'open' AND id != ?"
    );
    $stmt->execute([$oppositeType, $newItemId]);
    $candidates = $stmt->fetchAll();

    $stmtInsert = $pdo->prepare(
        "INSERT IGNORE INTO matches (lost_item_id, found_item_id, score) VALUES (?, ?, ?)"
    );

    foreach ($candidates as $candidate) {
        if ($newItem['type'] === 'lost') {
            $lostItem  = $newItem;
            $foundItem = $candidate;
        } else {
            $lostItem  = $candidate;
            $foundItem = $newItem;
        }

        $score = compute_score($lostItem, $foundItem);

        if ($score < MATCH_THRESHOLD) {
            continue;
        }

        $stmtInsert->execute([$lostItem['id'], $foundItem['id'], $score]);

        // Best-effort email — never break the posting flow
        _notify_match_pair($lostItem, $foundItem, $score);
    }
}

// ------------------------------------------------------------------
// Internal helpers
// ------------------------------------------------------------------

function _get_user_by_id(int $id): ?array
{
    static $cache = [];
    if (!isset($cache[$id])) {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $cache[$id] = $stmt->fetch() ?: null;
    }
    return $cache[$id];
}

function _notify_match_pair(array $lostItem, array $foundItem, float $score): void
{
    try {
        $lostUser  = _get_user_by_id((int)$lostItem['user_id']);
        $foundUser = _get_user_by_id((int)$foundItem['user_id']);

        if (!$lostUser || !$foundUser) {
            return;
        }

        $pct     = (int)round($score * 100);
        $subject = "Potential Match Found ({$pct}%) — Campus Lost & Found";

        $bodyLost = "Hello {$lostUser['name']},\n\n"
            . "Good news! A potential match was found for your lost item.\n\n"
            . "Your lost item : {$lostItem['title']}\n"
            . "Matched with   : {$foundItem['title']}\n"
            . "Match score    : {$pct}%\n\n"
            . "Log in to the portal to view details and raise a claim:\n"
            . "  http://localhost" . BASE_URL . "/user/matches.php\n\n"
            . "— Campus Lost & Found";

        $bodyFound = "Hello {$foundUser['name']},\n\n"
            . "An item you reported as found may belong to someone.\n\n"
            . "Item you found : {$foundItem['title']}\n"
            . "Matched with   : {$lostItem['title']}\n"
            . "Match score    : {$pct}%\n\n"
            . "The owner may raise a claim through the portal. No action needed from you now.\n"
            . "  http://localhost" . BASE_URL . "/user/matches.php\n\n"
            . "— Campus Lost & Found";

        send_mail($subject, $bodyLost,  [$lostUser['email']  => $lostUser['name']]);
        send_mail($subject, $bodyFound, [$foundUser['email'] => $foundUser['name']]);
    } catch (Throwable $e) {
        log_error('Match email failed: ' . $e->getMessage(), "lost#{$lostItem['id']} found#{$foundItem['id']}");
    }
}
