<?php

namespace App\Services\Assistant;

use App\Models\AssistantChange;
use App\Models\AssistantNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Tools the assistant can call. Read tools run immediately; anything that
 * changes data, code or the database schema waits for the user's approval.
 */
class AssistantTools
{
    /** Tools that change something and therefore need the user's approval first. */
    public const APPROVAL_REQUIRED = ['api_write', 'edit_file', 'write_file', 'run_migrations'];

    public function __construct(private ProjectFiles $files) {}

    public function definitions(): array
    {
        $tools = [
            [
                'name' => 'api_get',
                'description' => 'Read data from the CRM through its own REST API (GET only). Path is relative to /api, e.g. "/stores?sort=urgency", "/stores/3?period=30", "/dashboard", "/follow-ups?bucket=overdue", "/predictions". Prefer this over raw SQL for business questions, because it applies the same stock-estimate rules the app shows.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string', 'description' => 'Path under /api, including any query string.'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'api_write',
                'description' => 'Change CRM data through its own REST API: POST, PUT or DELETE. All the app\'s validation and follow-up scheduling apply. The user must approve each call before it runs, so give a one-line plain-language summary of the effect.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'method' => ['type' => 'string', 'enum' => ['POST', 'PUT', 'DELETE']],
                        'path' => ['type' => 'string', 'description' => 'Path under /api, e.g. "/stores/3/sales".'],
                        'body' => ['type' => 'object', 'description' => 'JSON body for POST/PUT.'],
                        'summary' => ['type' => 'string', 'description' => 'What this does, for the approval card.'],
                    ],
                    'required' => ['method', 'path', 'summary'],
                ],
            ],
            [
                'name' => 'describe_database',
                'description' => 'Live MySQL schema. Without a table: every table with its columns and row count. With a table: columns, types, nullability, defaults, indexes and foreign keys.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['table' => ['type' => 'string']],
                ],
            ],
            [
                'name' => 'query_database',
                'description' => 'Run one read-only SQL statement (SELECT, SHOW, DESCRIBE, EXPLAIN or WITH ... SELECT) against the CRM database. Runs inside a read-only transaction; at most 200 rows are returned. Use for analysis the API does not offer.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['sql' => ['type' => 'string']],
                    'required' => ['sql'],
                ],
            ],
            [
                'name' => 'list_files',
                'description' => 'List a folder of the project source (relative to the project root, e.g. "backend/app/Http/Controllers" or "frontend/src/pages"). Empty path lists the root.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['path' => ['type' => 'string']],
                ],
            ],
            [
                'name' => 'read_file',
                'description' => 'Read a source file with line numbers. Optionally a line range. Always read a file before editing it.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'start_line' => ['type' => 'integer'],
                        'end_line' => ['type' => 'integer'],
                    ],
                    'required' => ['path'],
                ],
            ],
            [
                'name' => 'search_code',
                'description' => 'Search the project source for a case-insensitive regular expression. Returns up to 100 matching lines as path:line: text.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'pattern' => ['type' => 'string'],
                        'path' => ['type' => 'string', 'description' => 'Folder to limit the search to, e.g. "frontend/src".'],
                    ],
                    'required' => ['pattern'],
                ],
            ],
            [
                'name' => 'save_note',
                'description' => 'Remember a durable fact for future conversations: a business rule, a preference of the user, or a non-obvious fact about this system. Not for things already visible in the code or schema. Keep each note to one or two sentences.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['note' => ['type' => 'string']],
                    'required' => ['note'],
                ],
            ],
            [
                'name' => 'delete_note',
                'description' => 'Delete a saved note that is wrong or outdated, by its id.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer']],
                    'required' => ['id'],
                ],
            ],
        ];

        if (config('assistant.allow_code_edits')) {
            $tools[] = [
                'name' => 'edit_file',
                'description' => 'Replace one exact, unique snippet in an existing source file. old_string must match the file exactly (including indentation) and occur once. Needs the user\'s approval; a backup is kept.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'old_string' => ['type' => 'string'],
                        'new_string' => ['type' => 'string'],
                        'summary' => ['type' => 'string', 'description' => 'Why this change, for the approval card.'],
                    ],
                    'required' => ['path', 'old_string', 'new_string', 'summary'],
                ],
            ];
            $tools[] = [
                'name' => 'write_file',
                'description' => 'Create a new source file, or fully replace a small one. Use edit_file for changes to existing files. New migrations go in backend/database/migrations with a timestamped name later than the existing ones. Needs approval; a backup is kept.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => ['type' => 'string'],
                        'content' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                    ],
                    'required' => ['path', 'content', 'summary'],
                ],
            ];
            $tools[] = [
                'name' => 'run_migrations',
                'description' => 'Run pending database migrations (php artisan migrate). Use after adding a migration file. Needs approval.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['summary' => ['type' => 'string']],
                    'required' => ['summary'],
                ],
            ];
        }

        return $tools;
    }

    public function requiresApproval(string $name): bool
    {
        return in_array($name, self::APPROVAL_REQUIRED, true);
    }

    /** What the approval card shows the user. Validates enough to catch obvious problems early. */
    public function preview(string $name, array $input): array
    {
        try {
            return match ($name) {
                'api_write' => [
                    'title' => $input['summary'] ?? 'Change CRM data',
                    'detail' => strtoupper($input['method'] ?? '?').' /api'.($input['path'] ?? '')
                        .(empty($input['body']) ? '' : "\n".json_encode($input['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'kind' => 'data',
                ],
                'edit_file' => $this->previewEdit($input),
                'write_file' => $this->previewWrite($input),
                'run_migrations' => [
                    'title' => $input['summary'] ?? 'Run database migrations',
                    'detail' => "php artisan migrate\n\nPending:\n".$this->pendingMigrations(),
                    'kind' => 'schema',
                ],
                default => ['title' => $name, 'detail' => json_encode($input), 'kind' => 'other'],
            };
        } catch (Throwable $e) {
            return ['title' => $input['summary'] ?? $name, 'detail' => 'Cannot apply: '.$e->getMessage(), 'kind' => 'error'];
        }
    }

    /**
     * Run a tool. Returns [string $content, bool $isError].
     */
    public function run(string $name, mixed $input, ?int $conversationId = null): array
    {
        try {
            if (! is_array($input)) {
                throw new RuntimeException('Tool input must be a JSON object.');
            }
            $this->validateInput($name, $input);

            $result = match ($name) {
                'api_get' => $this->apiCall('GET', $input['path']),
                'api_write' => $this->logged($conversationId, $name, $input['summary'], strtoupper($input['method']).' '.$input['path'],
                    fn () => [$this->apiCall(strtoupper($input['method']), $input['path'], $input['body'] ?? []), null]),
                'describe_database' => $this->describeDatabase($input['table'] ?? null),
                'query_database' => $this->queryDatabase($input['sql']),
                'list_files' => $this->listFiles($input['path'] ?? ''),
                'read_file' => $this->readFile($input['path'], $input['start_line'] ?? null, $input['end_line'] ?? null),
                'search_code' => $this->searchCode($input['pattern'], $input['path'] ?? ''),
                'save_note' => $this->saveNote($input['note'], $conversationId),
                'delete_note' => $this->deleteNote((int) $input['id']),
                'edit_file' => $this->logged($conversationId, $name, $input['summary'], $input['path'], fn () => $this->editFile($input)),
                'write_file' => $this->logged($conversationId, $name, $input['summary'], $input['path'], fn () => $this->writeFile($input)),
                'run_migrations' => $this->logged($conversationId, $name, $input['summary'], 'database', fn () => [$this->runMigrations(), null]),
                default => throw new RuntimeException("Unknown tool: {$name}"),
            };

            return [$this->truncate($result), false];
        } catch (Throwable $e) {
            return [$e->getMessage(), true];
        }
    }

    public function logRejection(?int $conversationId, string $name, array $input, string $reason): void
    {
        AssistantChange::create([
            'conversation_id' => $conversationId,
            'tool' => $name,
            'summary' => Str::limit($input['summary'] ?? '', 490),
            'target' => Str::limit($input['path'] ?? '', 490),
            'status' => 'rejected',
            'result' => $reason,
        ]);
    }

    // ---- input validation -------------------------------------------------

    /** Inputs may arrive truncated or malformed; check required string fields before acting. */
    private function validateInput(string $name, array $input): void
    {
        $definition = collect($this->definitions())->firstWhere('name', $name);
        if (! $definition) {
            throw new RuntimeException("Tool not available: {$name}");
        }
        foreach ($definition['input_schema']['required'] ?? [] as $field) {
            if (! array_key_exists($field, $input)) {
                throw new RuntimeException("Missing required field: {$field}");
            }
        }
        foreach ($definition['input_schema']['properties'] as $field => $spec) {
            if (! array_key_exists($field, $input) || $input[$field] === null) {
                continue;
            }
            $ok = match ($spec['type']) {
                'string' => is_string($input[$field]),
                'integer' => is_int($input[$field]) || (is_string($input[$field]) && ctype_digit($input[$field])),
                'object' => is_array($input[$field]),
                default => true,
            };
            if (! $ok) {
                throw new RuntimeException("Field {$field} must be of type {$spec['type']}.");
            }
            if (isset($spec['enum']) && ! in_array(strtoupper($input[$field]), $spec['enum'], true)) {
                throw new RuntimeException("Field {$field} must be one of: ".implode(', ', $spec['enum']));
            }
        }
    }

    // ---- API --------------------------------------------------------------

    /** Dispatch a request to the app's own API routes, so all validation and business rules apply. */
    private function apiCall(string $method, string $path, array $body = []): string
    {
        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#^/api(?=/|$)#', '', $path);
        if (str_starts_with($path, '/assistant')) {
            throw new RuntimeException('The assistant cannot call its own endpoints.');
        }

        // Same sign-in as the person chatting, so the API applies their permissions.
        $request = Request::create('/api'.$path, $method, [], [], [], array_filter([
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => request()->header('Authorization'),
        ]), $method === 'GET' ? null : json_encode($body));
        $request->setUserResolver(request()->getUserResolver());

        // Resolving the HTTP kernel registers the "api" middleware group on the router.
        app(\Illuminate\Contracts\Http\Kernel::class);

        $previous = app('request');
        app()->instance('request', $request);
        try {
            $response = app('router')->dispatch($request);
            $status = $response->getStatusCode();
            $content = $response->getContent();
        } catch (ValidationException $e) {
            $status = 422;
            $content = json_encode(['message' => $e->getMessage(), 'errors' => $e->errors()]);
        } catch (HttpExceptionInterface $e) {
            $status = $e->getStatusCode();
            $content = json_encode(['message' => $e->getMessage() ?: 'HTTP error']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $status = 404;
            $content = json_encode(['message' => 'Record not found']);
        } finally {
            app()->instance('request', $previous);
        }

        if ($status >= 400) {
            throw new RuntimeException("HTTP {$status}: {$content}");
        }

        return "HTTP {$status}\n".($content === '' ? '(no content)' : $content);
    }

    // ---- database ---------------------------------------------------------

    private function describeDatabase(?string $table): string
    {
        $db = DB::getDatabaseName();

        if (! $table) {
            $lines = [];
            foreach (Schema::getTables() as $t) {
                $name = $t['name'];
                $columns = collect(Schema::getColumns($name))->map(fn ($c) => $c['name'].' '.$c['type_name'])->implode(', ');
                $count = DB::table($name)->count();
                $lines[] = "{$name} ({$count} rows): {$columns}";
            }

            return implode("\n", $lines);
        }

        if (! Schema::hasTable($table)) {
            throw new RuntimeException("No table named {$table} in {$db}.");
        }

        return json_encode([
            'table' => $table,
            'columns' => Schema::getColumns($table),
            'indexes' => Schema::getIndexes($table),
            'foreign_keys' => Schema::getForeignKeys($table),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function queryDatabase(string $sql): string
    {
        $sql = trim($sql);
        $sql = rtrim($sql, "; \n\r\t");
        if (str_contains($sql, ';')) {
            throw new RuntimeException('Only one statement is allowed.');
        }
        if (! preg_match('/^(select|show|describe|desc|explain|with)\b/i', $sql)) {
            throw new RuntimeException('Only read-only statements (SELECT, SHOW, DESCRIBE, EXPLAIN, WITH) are allowed. Use api_write to change data.');
        }
        if (preg_match('/\b(into\s+(out|dump)file|load_file|sleep|benchmark|get_lock|for\s+update|lock\s+in\s+share)\b/i', $sql)) {
            throw new RuntimeException('That SQL feature is not allowed here.');
        }
        // Passwords, sign-in tokens and server tables never leave the database through the assistant.
        if (preg_match('/\b(users|personal_access_tokens|password_reset_tokens|sessions|mysql|information_schema|performance_schema)\b/i', $sql)) {
            throw new RuntimeException('That table holds account or server data and cannot be queried here. Use the Accounts page instead.');
        }

        // MySQL/MariaDB enforce read-only at the transaction level; the rollback covers everything else.
        $pdo = DB::connection()->getPdo();
        $readOnly = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) && DB::transactionLevel() === 0;
        $readOnly ? $pdo->exec('START TRANSACTION READ ONLY') : DB::beginTransaction();
        try {
            $rows = DB::select($sql);
        } finally {
            $readOnly ? $pdo->exec('ROLLBACK') : DB::rollBack();
        }

        $total = count($rows);
        $rows = array_slice($rows, 0, 200);

        return json_encode(['row_count' => $total, 'rows' => $rows], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            .($total > 200 ? "\n(only the first 200 rows shown)" : '');
    }

    // ---- files ------------------------------------------------------------

    private function listFiles(string $path): string
    {
        $relative = trim($path) === '' ? '' : $this->files->normalize($path);
        $absolute = $relative === '' ? $this->files->root() : $this->files->absolute($relative);
        if ($relative !== '' && ! $this->files->isReadable($relative)) {
            throw new RuntimeException("Not readable by the assistant: {$relative}");
        }
        if (! is_dir($absolute)) {
            throw new RuntimeException("Not a folder: {$relative}");
        }

        $entries = [];
        foreach (scandir($absolute) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = ltrim($relative.'/'.$entry, '/');
            if (! $this->files->isReadable($child)) {
                continue;
            }
            $entries[] = is_dir($this->files->absolute($child)) ? $child.'/' : $child.' ('.filesize($this->files->absolute($child)).' bytes)';
        }

        return $entries ? implode("\n", $entries) : '(empty)';
    }

    private function readFile(string $path, mixed $start, mixed $end): string
    {
        $relative = $this->files->normalize($path);
        $this->files->assertReadable($relative);
        $absolute = $this->files->absolute($relative);
        if (! is_file($absolute)) {
            throw new RuntimeException("File not found: {$relative}");
        }

        $lines = preg_split('/\r\n|\n/', file_get_contents($absolute));
        $start = max(1, (int) ($start ?? 1));
        $end = min(count($lines), (int) ($end ?? count($lines)));

        $out = [];
        for ($i = $start; $i <= $end; $i++) {
            $out[] = str_pad((string) $i, 5, ' ', STR_PAD_LEFT).'  '.$lines[$i - 1];
        }

        return "{$relative} (lines {$start}-{$end} of ".count($lines).")\n".implode("\n", $out);
    }

    private function searchCode(string $pattern, string $path): string
    {
        $regex = '/'.str_replace('/', '\/', $pattern).'/i';
        if (@preg_match($regex, '') === false) {
            throw new RuntimeException('Invalid regular expression.');
        }

        $roots = trim($path) === '' ? config('assistant.readable') : [$this->files->normalize($path)];
        $matches = [];
        foreach ($roots as $root) {
            if (! $this->files->isReadable($root)) {
                continue;
            }
            foreach ($this->walk($root) as $file) {
                $lines = preg_split('/\r\n|\n/', (string) file_get_contents($this->files->absolute($file)));
                foreach ($lines as $n => $line) {
                    if (preg_match($regex, $line)) {
                        $matches[] = $file.':'.($n + 1).': '.Str::limit(trim($line), 200);
                        if (count($matches) >= 100) {
                            return implode("\n", $matches)."\n(stopped at 100 matches)";
                        }
                    }
                }
            }
        }

        return $matches ? implode("\n", $matches) : 'No matches.';
    }

    /** @return iterable<string> readable source files under a relative path */
    private function walk(string $relative): iterable
    {
        $absolute = $this->files->absolute($relative);
        if (is_file($absolute)) {
            yield $relative;

            return;
        }
        if (! is_dir($absolute)) {
            return;
        }
        foreach (scandir($absolute) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $relative.'/'.$entry;
            if (! $this->files->isReadable($child)) {
                continue;
            }
            if (is_dir($this->files->absolute($child))) {
                yield from $this->walk($child);
            } elseif (preg_match('/\.(php|jsx?|tsx?|css|json|md|html)$/i', $entry) && filesize($this->files->absolute($child)) < 500000) {
                yield $child;
            }
        }
    }

    private function previewEdit(array $input): array
    {
        $relative = $this->files->normalize($input['path'] ?? '');
        $this->files->assertEditable($relative);
        $current = $this->currentContent($relative, true);
        $this->assertUnique($current, $input['old_string'] ?? '', $relative);

        return [
            'title' => $input['summary'] ?? 'Edit '.$relative,
            'detail' => $relative,
            'kind' => 'code',
            'old' => $input['old_string'],
            'new' => $input['new_string'] ?? '',
        ];
    }

    private function previewWrite(array $input): array
    {
        $relative = $this->files->normalize($input['path'] ?? '');
        $this->files->assertEditable($relative);
        $exists = is_file($this->files->absolute($relative));

        return [
            'title' => $input['summary'] ?? 'Write '.$relative,
            'detail' => ($exists ? 'Replaces existing file ' : 'Creates new file ').$relative,
            'kind' => 'code',
            'old' => $exists ? file_get_contents($this->files->absolute($relative)) : '',
            'new' => $input['content'] ?? '',
        ];
    }

    /** @return array{0: string, 1: ?string} [result, backup path] */
    private function editFile(array $input): array
    {
        $relative = $this->files->normalize($input['path']);
        $this->files->assertEditable($relative);
        $current = $this->currentContent($relative, true);
        $this->assertUnique($current, $input['old_string'], $relative);

        $backup = $this->backup($relative);
        $updated = str_replace($input['old_string'], $input['new_string'], $current);
        file_put_contents($this->files->absolute($relative), $updated);

        return ["Edited {$relative}.", $backup];
    }

    /** @return array{0: string, 1: ?string} */
    private function writeFile(array $input): array
    {
        $relative = $this->files->normalize($input['path']);
        $this->files->assertEditable($relative);
        $absolute = $this->files->absolute($relative);

        $backup = is_file($absolute) ? $this->backup($relative) : null;
        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }
        file_put_contents($absolute, $input['content']);

        return [($backup ? 'Replaced ' : 'Created ').$relative.'.', $backup];
    }

    private function currentContent(string $relative, bool $mustExist): string
    {
        $absolute = $this->files->absolute($relative);
        if (! is_file($absolute)) {
            if ($mustExist) {
                throw new RuntimeException("File not found: {$relative}. Use write_file to create it.");
            }

            return '';
        }

        return file_get_contents($absolute);
    }

    private function assertUnique(string $content, string $needle, string $relative): void
    {
        if ($needle === '') {
            throw new RuntimeException('old_string is empty.');
        }
        $count = substr_count($content, $needle);
        if ($count === 0) {
            throw new RuntimeException("old_string was not found in {$relative}. Read the file again and copy the text exactly.");
        }
        if ($count > 1) {
            throw new RuntimeException("old_string occurs {$count} times in {$relative}; include more surrounding lines so it is unique.");
        }
    }

    private function backup(string $relative): string
    {
        $target = storage_path('app/assistant-backups/'.now()->format('Ymd-His').'-'.Str::random(4).'/'.$relative);
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        copy($this->files->absolute($relative), $target);

        return $target;
    }

    // ---- migrations -------------------------------------------------------

    private function pendingMigrations(): string
    {
        $ran = DB::table('migrations')->pluck('migration')->all();
        $pending = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($f) => basename($f, '.php'))
            ->reject(fn ($m) => in_array($m, $ran, true))
            ->values();

        return $pending->isEmpty() ? '(none)' : $pending->implode("\n");
    }

    private function runMigrations(): string
    {
        if (! config('assistant.allow_code_edits')) {
            throw new RuntimeException('Schema changes are disabled (ASSISTANT_ALLOW_CODE_EDITS).');
        }
        Artisan::call('migrate', ['--force' => true]);

        return trim(Artisan::output()) ?: 'Nothing to migrate.';
    }

    // ---- notes ------------------------------------------------------------

    private function saveNote(string $note, ?int $conversationId): string
    {
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 1000) {
            throw new RuntimeException('A note must be 1–1000 characters.');
        }
        $saved = AssistantNote::create(['note' => $note, 'conversation_id' => $conversationId]);

        return "Saved note #{$saved->id}.";
    }

    private function deleteNote(int $id): string
    {
        $deleted = AssistantNote::whereKey($id)->delete();

        return $deleted ? "Deleted note #{$id}." : "No note #{$id}.";
    }

    // ---- helpers ----------------------------------------------------------

    /** Run an approved change and record it in the audit log. */
    private function logged(?int $conversationId, string $tool, ?string $summary, ?string $target, callable $action): string
    {
        try {
            [$result, $backup] = $action();
            AssistantChange::create([
                'conversation_id' => $conversationId,
                'tool' => $tool,
                'summary' => Str::limit((string) $summary, 490),
                'target' => Str::limit((string) $target, 490),
                'backup_path' => $backup,
                'status' => 'applied',
                'result' => Str::limit($result, 5000),
            ]);

            return $result;
        } catch (Throwable $e) {
            AssistantChange::create([
                'conversation_id' => $conversationId,
                'tool' => $tool,
                'summary' => Str::limit((string) $summary, 490),
                'target' => Str::limit((string) $target, 490),
                'status' => 'failed',
                'result' => Str::limit($e->getMessage(), 5000),
            ]);
            throw $e;
        }
    }

    private function truncate(string $text): string
    {
        $max = config('assistant.max_result_chars');

        return mb_strlen($text) > $max
            ? mb_substr($text, 0, $max)."\n…(truncated; ask for a narrower range)"
            : $text;
    }
}
