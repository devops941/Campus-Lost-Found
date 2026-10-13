<?php
/**
 * Seed script — run ONCE after importing lostfound.sql
 *   php sql/seed.php
 * Must be run from the project root (d:/Lostcampus/lost-found/).
 */

define('SEED_ROOT', dirname(__DIR__));
require_once SEED_ROOT . '/config/db.php';

$pdo = get_db();

echo "Seeding lostfound database...\n";

// ------------------------------------------------------------------
// 1. Users
// ------------------------------------------------------------------
$users = [
    [
        'name'  => 'Admin User',
        'email' => 'admin@college.edu',
        'pass'  => 'Admin@123',
        'phone' => '9000000001',
        'role'  => 'admin',
    ],
    [
        'name'  => 'John Doe',
        'email' => 'john.doe@college.edu',
        'pass'  => 'User@123',
        'phone' => '9000000002',
        'role'  => 'user',
    ],
    [
        'name'  => 'Jane Smith',
        'email' => 'jane.smith@college.edu',
        'pass'  => 'User@123',
        'phone' => '9000000003',
        'role'  => 'user',
    ],
    [
        'name'  => 'Mike Jones',
        'email' => 'mike.jones@college.edu',
        'pass'  => 'User@123',
        'phone' => '9000000004',
        'role'  => 'user',
    ],
    [
        'name'  => 'Sara Wilson',
        'email' => 'sara.wilson@college.edu',
        'pass'  => 'User@123',
        'phone' => '9000000005',
        'role'  => 'user',
    ],
];

$stmtUser = $pdo->prepare(
    "INSERT IGNORE INTO users (name, email, password, phone, role) VALUES (?,?,?,?,?)"
);

$userIds = [];
foreach ($users as $u) {
    $hash = password_hash($u['pass'], PASSWORD_BCRYPT);
    $stmtUser->execute([$u['name'], $u['email'], $hash, $u['phone'], $u['role']]);
    $userIds[$u['email']] = (int)$pdo->lastInsertId();
    echo "  User: {$u['email']} / {$u['pass']}\n";
}

// Re-fetch IDs in case rows already existed (INSERT IGNORE path)
$rows = $pdo->query("SELECT id, email FROM users")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($rows as $email => $id) {
    $userIds[$email] = (int)$id;
}

$admin = $userIds['admin@college.edu'];
$john  = $userIds['john.doe@college.edu'];
$jane  = $userIds['jane.smith@college.edu'];
$mike  = $userIds['mike.jones@college.edu'];
$sara  = $userIds['sara.wilson@college.edu'];

// ------------------------------------------------------------------
// 2. Items  (12+, all categories, mix of lost/found)
// ------------------------------------------------------------------
$items = [
    // LOST
    [
        'user_id'     => $john,
        'type'        => 'lost',
        'category'    => 'electronics',
        'title'       => 'Lost black Casio calculator',
        'description' => 'Black Casio scientific calculator, model fx-991 series, lost near the exam hall.',
        'location'    => 'Exam Hall Block B',
        'item_date'   => '2026-10-01',
        'status'      => 'open',
    ],
    [
        'user_id'     => $jane,
        'type'        => 'lost',
        'category'    => 'bottle',
        'title'       => 'Lost blue water bottle',
        'description' => 'Blue 1-litre stainless steel water bottle with college sticker on the side.',
        'location'    => 'Canteen',
        'item_date'   => '2026-10-03',
        'status'      => 'open',
    ],
    [
        'user_id'     => $mike,
        'type'        => 'lost',
        'category'    => 'id_card',
        'title'       => 'Lost student ID card',
        'description' => 'College ID card with name Mike Jones, roll number 2023CS045.',
        'location'    => 'Library',
        'item_date'   => '2026-10-04',
        'status'      => 'open',
    ],
    [
        'user_id'     => $sara,
        'type'        => 'lost',
        'category'    => 'books',
        'title'       => 'Lost physics textbook',
        'description' => 'HC Verma Concepts of Physics Part 1, with yellow highlighter markings throughout.',
        'location'    => 'Reading Room',
        'item_date'   => '2026-10-05',
        'status'      => 'open',
    ],
    [
        'user_id'     => $john,
        'type'        => 'lost',
        'category'    => 'bag',
        'title'       => 'Lost black laptop bag',
        'description' => 'HP branded black laptop bag with a red zipper pull and initials JD on the front pocket.',
        'location'    => 'Computer Lab',
        'item_date'   => '2026-10-06',
        'status'      => 'open',
    ],
    [
        'user_id'     => $jane,
        'type'        => 'lost',
        'category'    => 'keys',
        'title'       => 'Lost key bunch with hostel tag',
        'description' => 'Three keys on a metal ring with a pink hostel tag marked H-204.',
        'location'    => 'Girls Hostel Gate',
        'item_date'   => '2026-10-07',
        'status'      => 'open',
    ],
    [
        'user_id'     => $mike,
        'type'        => 'lost',
        'category'    => 'electronics',
        'title'       => 'Lost Sony earphones',
        'description' => 'Sony WH-1000XM4 wired earphones, black, left earcup has a small scratch.',
        'location'    => 'Sports Ground',
        'item_date'   => '2026-10-08',
        'status'      => 'open',
    ],

    // FOUND
    [
        'user_id'          => $sara,
        'type'             => 'found',
        'category'         => 'electronics',
        'title'            => 'Found Casio fx-991 calculator near exam hall',
        'description'      => 'Found a Casio scientific calculator fx-991 ES plus near exam hall entrance, black colour.',
        'location'         => 'Exam Hall Entrance',
        'item_date'        => '2026-10-02',
        'status'           => 'open',
        'verify_question'  => 'What is written on the back of the calculator?',
        'verify_answer'    => 'john',
    ],
    [
        'user_id'          => $john,
        'type'             => 'found',
        'category'         => 'bottle',
        'title'            => 'Found blue water bottle in canteen',
        'description'      => 'Found a blue stainless steel water bottle in the canteen, has a sticker on it.',
        'location'         => 'Canteen',
        'item_date'        => '2026-10-03',
        'status'           => 'open',
        'verify_question'  => 'What sticker is on the bottle?',
        'verify_answer'    => 'college logo',
    ],
    [
        'user_id'          => $jane,
        'type'             => 'found',
        'category'         => 'id_card',
        'title'            => 'Found college ID card in library',
        'description'      => 'Found a college student ID card near the periodicals section in the library.',
        'location'         => 'Library Periodicals',
        'item_date'        => '2026-10-04',
        'status'           => 'open',
        'verify_question'  => 'What is the roll number on the card?',
        'verify_answer'    => '2023CS045',
    ],
    [
        'user_id'          => $sara,
        'type'             => 'found',
        'category'         => 'keys',
        'title'            => 'Found keys near hostel gate',
        'description'      => 'Found three keys on a ring near the main gate, tag attached to it.',
        'location'         => 'Main Gate',
        'item_date'        => '2026-10-07',
        'status'           => 'open',
        'verify_question'  => 'What colour is the tag on the keys?',
        'verify_answer'    => 'pink',
    ],
    [
        'user_id'          => $mike,
        'type'             => 'found',
        'category'         => 'bag',
        'title'            => 'Found laptop bag near computer lab',
        'description'      => 'Found a black laptop bag with initials on the front pocket, left outside computer lab.',
        'location'         => 'Computer Lab Corridor',
        'item_date'        => '2026-10-06',
        'status'           => 'claimed',
        'verify_question'  => 'What initials are on the front pocket of the bag?',
        'verify_answer'    => 'JD',
    ],
    [
        'user_id'          => $john,
        'type'             => 'found',
        'category'         => 'books',
        'title'            => 'Found physics book in reading room',
        'description'      => 'Found HC Verma part 1 on reading room table, has yellow highlighting throughout.',
        'location'         => 'Reading Room Table 3',
        'item_date'        => '2026-10-05',
        'status'           => 'returned',
        'verify_question'  => 'What colour highlighter was used inside the book?',
        'verify_answer'    => 'yellow',
    ],
];

$stmtItem = $pdo->prepare(
    "INSERT INTO items
        (user_id, type, category, title, description, location, item_date, status,
         verify_question, verify_answer)
     VALUES (?,?,?,?,?,?,?,?,?,?)"
);

$itemIds = [];
foreach ($items as $it) {
    $stmtItem->execute([
        $it['user_id'], $it['type'], $it['category'],
        $it['title'], $it['description'] ?? null,
        $it['location'] ?? null, $it['item_date'] ?? null,
        $it['status'] ?? 'open',
        $it['verify_question'] ?? null,
        $it['verify_answer'] ?? null,
    ]);
    $itemIds[] = (int)$pdo->lastInsertId();
    echo "  Item: [{$it['type']}] {$it['title']}\n";
}

// Fetch item IDs by title for match/claim wiring
$idMap = [];
$rows  = $pdo->query("SELECT id, title FROM items")->fetchAll();
foreach ($rows as $r) {
    $idMap[$r['title']] = (int)$r['id'];
}

$lostCalc  = $idMap['Lost black Casio calculator'];
$foundCalc = $idMap['Found Casio fx-991 calculator near exam hall'];
$lostBottle  = $idMap['Lost blue water bottle'];
$foundBottle = $idMap['Found blue water bottle in canteen'];
$lostId    = $idMap['Lost student ID card'];
$foundId   = $idMap['Found college ID card in library'];
$lostKeys  = $idMap['Lost key bunch with hostel tag'];
$foundKeys = $idMap['Found keys near hostel gate'];
$lostBag   = $idMap['Lost black laptop bag'];
$foundBag  = $idMap['Found laptop bag near computer lab'];
$lostBook  = $idMap['Lost physics textbook'];
$foundBook = $idMap['Found physics book in reading room'];

// ------------------------------------------------------------------
// 3. Matches
// ------------------------------------------------------------------
$matches = [
    [$lostCalc,   $foundCalc,   0.85],
    [$lostBottle, $foundBottle, 0.72],
    [$lostId,     $foundId,     0.90],
    [$lostKeys,   $foundKeys,   0.68],
    [$lostBag,    $foundBag,    0.80],
    [$lostBook,   $foundBook,   0.75],
];

$stmtMatch = $pdo->prepare(
    "INSERT IGNORE INTO matches (lost_item_id, found_item_id, score) VALUES (?,?,?)"
);

$matchIds = [];
foreach ($matches as [$l, $f, $s]) {
    $stmtMatch->execute([$l, $f, $s]);
    $matchIds["$l-$f"] = (int)$pdo->lastInsertId();
    echo "  Match: lost#$l <-> found#$f  score=$s\n";
}

// Re-fetch match IDs
$mRows = $pdo->query("SELECT id, lost_item_id, found_item_id FROM matches")->fetchAll();
foreach ($mRows as $mr) {
    $matchIds["{$mr['lost_item_id']}-{$mr['found_item_id']}"] = (int)$mr['id'];
}

$mCalc  = $matchIds["$lostCalc-$foundCalc"];
$mBag   = $matchIds["$lostBag-$foundBag"];
$mBook  = $matchIds["$lostBook-$foundBook"];

// ------------------------------------------------------------------
// 4. Claims (pending / approved / rejected)
// ------------------------------------------------------------------
$stmtClaim = $pdo->prepare(
    "INSERT IGNORE INTO claims
        (match_id, claimant_id, answer_given, status, reviewed_by, reviewed_at, admin_note)
     VALUES (?,?,?,?,?,?,?)"
);

// Pending claim: John claims his calculator back
$stmtClaim->execute([
    $mCalc, $john, 'john', 'pending', null, null, null
]);
echo "  Claim: pending — John on calculator\n";

// Approved claim: John claims his bag (already 'claimed' status set on item)
$stmtClaim->execute([
    $mBag, $john, 'JD', 'approved', $admin, '2026-10-07 11:00:00', 'Verification answer matched. Item returned.'
]);
echo "  Claim: approved — John on bag\n";

// Rejected claim: Sara (incorrectly) claims the book
$stmtClaim->execute([
    $mBook, $sara, 'blue', 'rejected', $admin, '2026-10-07 12:30:00', 'Verification answer did not match.'
]);
echo "  Claim: rejected — Sara on physics book\n";

echo "\nSeed complete.\n";
echo "Login credentials:\n";
echo "  admin@college.edu  / Admin@123\n";
echo "  john.doe@college.edu / User@123\n";
echo "  jane.smith@college.edu / User@123\n";
echo "  mike.jones@college.edu / User@123\n";
echo "  sara.wilson@college.edu / User@123\n";
