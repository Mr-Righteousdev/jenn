<?php
// Drafts a curriculum mapping (course unit → skills) from course names, for units in programmes that are
// in use and have no mapping yet. It's a starting point for faculty staff to review on the Curriculum page,
// not a final answer: course names are short and the SIS has no course descriptions.
//
//   php src/scripts/suggest_mapping.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

function suggestMapping(PDO $db): int {
    // name pattern => [skill => weight]
    $rules = [
        '/\bpython\b/i'                                         => ['Python' => 1.0],
        '/\bjava\b(?!script)/i'                                 => ['Java' => 1.0],
        '/\bc\+\+|\bc programming|programming in c\b|structured programming/i' => ['C++' => 1.0],
        '/javascript|client.?side/i'                            => ['JavaScript' => 1.0],
        '/\bphp\b|server.?side|web programming/i'               => ['PHP' => 1.0, 'MySQL' => 0.25],
        '/web (design|development|technolog|page)|authoring|\bhtml\b|internet computing|e-?commerce/i' => ['HTML' => 0.75, 'CSS' => 0.5, 'JavaScript' => 0.25],
        '/database|\bdba\b|\bsql\b|data management/i'           => ['MySQL' => 0.75, 'PostgreSQL' => 0.5],
        '/network|tcp|data communication|telecommunication/i'   => ['TCP/IP' => 0.75, 'DNS' => 0.5],
        '/operating system|linux|unix/i'                        => ['Linux' => 0.75],
        '/system(s)? administration|server|cloud/i'             => ['Linux' => 0.75, 'Apache' => 0.5],
        '/mobile/i'                                             => ['Java' => 0.5, 'JavaScript' => 0.25],
        '/communication skills|english|writing|presentation/i'  => ['Communication' => 1.0],
        '/project|research|professional|industrial training|internship|entrepreneur|management|society|ethics/i' => ['Communication' => 0.5, 'Problem Solving' => 0.5],
        '/programming|algorithm|data structure|software engineering|analysis|design|mathematic|discrete|statistic|optimi|logic|calculus/i' => ['Problem Solving' => 0.75],
    ];
    $skillId = $db->query("SELECT skill_name, skill_id FROM skills")->fetchAll(PDO::FETCH_KEY_PAIR);
    $units = $db->query("SELECT DISTINCT cu.code, cu.name FROM course_units cu JOIN programme_units pu ON pu.course_code = cu.code
                         JOIN programmes p ON p.code = pu.programme_code
                         WHERE p.is_active AND NOT EXISTS (SELECT 1 FROM course_unit_skills c WHERE c.course_code = cu.code)")->fetchAll();
    $ins = $db->prepare("INSERT INTO course_unit_skills (course_code, skill_id, weight) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE weight = GREATEST(weight, VALUES(weight))");
    $mapped = 0;
    foreach ($units as $u) {
        $hit = false;
        foreach ($rules as $pattern => $skills) {
            if (!preg_match($pattern, $u['name'])) continue;
            foreach ($skills as $skill => $w) {
                if (isset($skillId[$skill])) { $ins->execute([$u['code'], $skillId[$skill], $w]); $hit = true; }
            }
        }
        $mapped += $hit;
    }
    return $mapped;
}

if (PHP_SAPI === 'cli' && realpath($argv[0]) === __FILE__) {
    $n = suggestMapping(Database::getConnection());
    echo "Drafted skills for $n course units. Review them on the Curriculum page." . PHP_EOL;
}
