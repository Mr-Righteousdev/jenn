<?php

require_once __DIR__ . '/../config/database.php';

class JobApi {
    private ?string $apiKey;
    private string $apiHost = 'jsearch.p.rapidapi.com';

    public function __construct() {
        $this->apiKey = getenv('RAPIDAPI_KEY') ?: null;
    }

    public function isConfigured(): bool {
        return $this->apiKey !== null;
    }

    public function fetchJobs(string $query = 'software developer', string $country = 'ug', int $numPages = 1): array {
        if ($this->isConfigured()) {
            return $this->fetchFromApi($query, $country, $numPages);
        }
        app_log('warning', 'RAPIDAPI_KEY is not set; using sample jobs instead of the live API');
        return ['success' => true, 'jobs' => $this->getSampleJobs()['jobs'], 'sample' => true];
    }

    /** One small live request, for the System health page. */
    public function testConnection(): array {
        if (!$this->isConfigured()) return ['success' => false, 'error' => 'RAPIDAPI_KEY is not set in .env, so the app uses sample jobs.'];
        $started = microtime(true);
        $r = $this->fetchFromApi('software developer', 'ug', 1);
        $r['ms'] = (int)round((microtime(true) - $started) * 1000);
        return $r;
    }

    private function fetchFromApi(string $query, string $country, int $numPages): array {
        $url = "https://{$this->apiHost}/search-v2?query=" . urlencode($query) . "&num_pages=$numPages&country=" . urlencode($country) . "&date_posted=all";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'x-rapidapi-host: ' . $this->apiHost,
                'x-rapidapi-key: ' . $this->apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Say exactly what went wrong: network problems, the API's own message, or an unexpected reply
        if ($response === false || $httpCode === 0) {
            $error = "Couldn't reach the job API ($curlError). Check the server's internet access, DNS and firewall.";
        } elseif ($httpCode !== 200) {
            $apiMessage = json_decode((string)$response, true)['message'] ?? trim(substr(strip_tags((string)$response), 0, 200));
            $hint = match (true) {
                $httpCode === 401 || $httpCode === 403 => ' Check RAPIDAPI_KEY and that this key is subscribed to the JSearch API on RapidAPI.',
                $httpCode === 429 => ' The monthly or per-second request limit was reached; wait or upgrade the RapidAPI plan.',
                $httpCode >= 500 => ' The job API is having problems; try again later.',
                default => '',
            };
            $error = "The job API returned HTTP $httpCode: " . rtrim($apiMessage ?: 'no message', '.') . '.' . $hint;
        } else {
            $data = json_decode((string)$response, true);
            if (!isset($data['data']['jobs']) || !is_array($data['data']['jobs'])) {
                $error = 'The job API replied, but not in the expected format (no data.jobs). The API may have changed.';
            } else {
                return ['success' => true, 'jobs' => $this->normalizeJobs($data['data']['jobs'])];
            }
        }
        app_log('error', 'Job API request failed', ['http' => $httpCode, 'error' => $error, 'query' => $query, 'country' => $country]);
        return ['success' => false, 'error' => $error, 'http' => $httpCode];
    }

    private function normalizeJobs(array $rawJobs): array {
        $jobs = [];
        foreach ($rawJobs as $j) {
            $jobs[] = [
                'title' => $j['job_title'] ?? 'Unknown',
                'company' => $j['employer_name'] ?? 'Unknown',
                'description' => $j['job_description'] ?? '',
                'url' => $j['job_apply_link'] ?? '#',
                'source' => $j['job_publisher'] ?? 'JSearch',
            ];
        }
        return $jobs;
    }

    private function getSampleJobs(): array {
        $samples = [
            [
                'title' => 'Junior Software Developer',
                'company' => 'TechSolutions Uganda',
                'description' => 'We are looking for a Junior Software Developer proficient in Python, JavaScript, and MySQL. Experience with Django or Flask is a plus. The ideal candidate has strong problem solving skills and can work with databases and web technologies.',
                'url' => '#',
                'source' => 'Sample',
            ],
            [
                'title' => 'IT Support Specialist',
                'company' => 'DataCore Ltd',
                'description' => 'Provide IT support for a mid-sized company. Requires knowledge of Linux server administration, TCP/IP networking, DNS configuration, and troubleshooting. Good communication skills essential.',
                'url' => '#',
                'source' => 'Sample',
            ],
            [
                'title' => 'Web Developer',
                'company' => 'Creative Digital Agency',
                'description' => 'Build modern web applications using HTML, CSS, JavaScript, and PHP. Experience with MySQL and Apache server configuration required. Must have problem solving ability and work well in a team.',
                'url' => '#',
                'source' => 'Sample',
            ],
            [
                'title' => 'Database Administrator',
                'company' => 'FinServe Bank',
                'description' => 'Manage and optimize MySQL and PostgreSQL databases. Perform backup, recovery, and performance tuning. Knowledge of Linux and networking fundamentals expected.',
                'url' => '#',
                'source' => 'Sample',
            ],
            [
                'title' => 'Network Engineer',
                'company' => 'ConnectTel ISP',
                'description' => 'Design and maintain network infrastructure. Must understand TCP/IP, DNS, DHCP, and routing protocols. Linux server configuration and scripting skills are an advantage.',
                'url' => '#',
                'source' => 'Sample',
            ],
            [
                'title' => 'Full Stack Developer',
                'company' => 'StartupHub',
                'description' => 'Develop end-to-end web solutions using Python, JavaScript, PHP, MySQL, and Linux. Must have strong communication skills and ability to work independently on complex problems.',
                'url' => '#',
                'source' => 'Sample',
            ],
            [
                'title' => 'Software Engineer Intern',
                'company' => 'Innovate Tech',
                'description' => 'Internship opportunity for students pursuing IT or CS. Learn Java, Python, web development, and database management. Mentorship provided by senior engineers.',
                'url' => '#',
                'source' => 'Sample',
            ],
            [
                'title' => 'Systems Administrator',
                'company' => 'EduNet University',
                'description' => 'Maintain university IT systems including Linux and Apache servers. Handle networking issues, manage user accounts, and ensure system security. Problem solving skills required.',
                'url' => '#',
                'source' => 'Sample',
            ],
        ];
        return ['success' => true, 'jobs' => $samples];
    }

    public static function storeJobs(array $jobs): array {
        $db = Database::getConnection();
        $stored = 0;
        $newIds = [];

        $skillCache = [];
        $stmt = $db->query("SELECT skill_id, skill_name, category FROM skills");
        foreach ($stmt->fetchAll() as $s) {
            $skillCache[strtolower($s['skill_name'])] = $s;
            $skillCache[strtolower($s['category'])] = $s;
        }

        $checkStmt = $db->prepare("SELECT job_id FROM jobs WHERE title = ? AND company = ? LIMIT 1");

        $db->beginTransaction();
        try {
            $insertJob = $db->prepare("INSERT INTO jobs (title, company, description, url, source, scraped_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
            $insertJobSkill = $db->prepare("INSERT INTO job_skills (job_id, skill_id, weight, keyword_match_pattern) VALUES (?, ?, ?, ?)");

            foreach ($jobs as $job) {
                $checkStmt->execute([$job['title'], $job['company']]);
                if ($checkStmt->fetch()) {
                    continue;
                }

                $insertJob->execute([$job['title'], $job['company'], $job['description'], $job['url'], $job['source']]);
                $jobId = (int) $db->lastInsertId();
                $newIds[] = $jobId;

                $matchedSkills = self::extractSkills($job['description'], $skillCache);
                foreach ($matchedSkills as $skillId => $weight) {
                    $insertJobSkill->execute([$jobId, $skillId, $weight, '']);
                }

                $stored++;
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            app_log('error', 'Saving fetched jobs failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => true, 'stored' => $stored, 'job_ids' => $newIds];
    }

    private static function extractSkills(string $description, array $skillCache): array {
        $description = strip_tags($description);
        $descriptionLower = strtolower($description);
        $matches = [];

        foreach ($skillCache as $key => $skill) {
            $skillId = $skill['skill_id'];
            $pattern = preg_quote($key, '/');
            $count = preg_match_all("/\b$pattern\b/i", $description);

            if ($count > 0) {
                $weight = min(1.0, 0.3 + ($count * 0.1));
                if (!isset($matches[$skillId]) || $weight > $matches[$skillId]) {
                    $matches[$skillId] = $weight;
                }
            }
        }

        arsort($matches);
        return $matches;
    }
}
