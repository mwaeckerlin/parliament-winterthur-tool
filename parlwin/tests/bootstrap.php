<?php

declare(strict_types=1);

/**
 * Test-Bootstrap für das Parlament Winterthur Plugin.
 *
 * Stellt minimale Stubs für Nextcloud- und PSR-Interfaces bereit,
 * sodass Unit-Tests ohne eine vollständige Nextcloud-Installation
 * ausgeführt werden können.
 *
 * Verwendung:
 *   phpunit --bootstrap tests/bootstrap.php tests/
 */

$appDir = dirname(__DIR__);

// ── PSR-Stubs ────────────────────────────────────────────────────────────────

if (!interface_exists('Psr\Log\LoggerInterface')) {
    // phpcs:ignore
    eval ('namespace Psr\Log; interface LoggerInterface {
        public function debug(string $message, array $context = []): void;
        public function info(string $message, array $context = []): void;
        public function notice(string $message, array $context = []): void;
        public function warning(string $message, array $context = []): void;
        public function error(string $message, array $context = []): void;
        public function critical(string $message, array $context = []): void;
        public function alert(string $message, array $context = []): void;
        public function emergency(string $message, array $context = []): void;
        public function log($level, string $message, array $context = []): void;
    }');
}

// ── Symfony-Console-Stubs ───────────────────────────────────────────────────

if (!interface_exists('Symfony\Component\Console\Input\InputInterface')) {
    // phpcs:ignore
    eval ('namespace Symfony\Component\Console\Input; interface InputInterface {
        public function getOption(string $name);
    }');
}

if (!class_exists('Symfony\Component\Console\Input\ArrayInput')) {
    // phpcs:ignore
    eval ('namespace Symfony\Component\Console\Input; class ArrayInput implements InputInterface {
        private array $parameters = [];
        public function __construct(array $parameters = []) { $this->parameters = $parameters; }
        public function setInteractive(bool $interactive): void {}
        public function getOption(string $name) {
            $key = "--" . $name;
            return $this->parameters[$key] ?? null;
        }
    }');
}

if (!interface_exists('Symfony\Component\Console\Output\OutputInterface')) {
    // phpcs:ignore
    eval ('namespace Symfony\Component\Console\Output; interface OutputInterface {
        public function writeln(string|array $messages, int $options = 0): void;
    }');
}

if (!class_exists('Symfony\Component\Console\Output\NullOutput')) {
    // phpcs:ignore
    eval ('namespace Symfony\Component\Console\Output; class NullOutput implements OutputInterface {
        public function writeln(string|array $messages, int $options = 0): void {}
    }');
}

if (!class_exists('Symfony\Component\Console\Input\InputOption')) {
    // phpcs:ignore
    eval ('namespace Symfony\Component\Console\Input; class InputOption {
        public const VALUE_NONE = 1;
        public const VALUE_REQUIRED = 2;
    }');
}

if (!interface_exists('OCP\AppFramework\Utility\ITimeFactory')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework\Utility; interface ITimeFactory {}');
}

if (!class_exists('OCP\BackgroundJob\TimedJob')) {
    // phpcs:ignore
    eval ('namespace OCP\BackgroundJob; class TimedJob {
        public const TIME_INSENSITIVE = 0;
        public function __construct(protected \OCP\AppFramework\Utility\ITimeFactory $time) {}
        protected function setInterval(int $seconds): void {}
        protected function setTimeSensitivity(int $sensitivity): void {}
    }');
}

if (!class_exists('Symfony\Component\Console\Command\Command')) {
    // phpcs:ignore
    eval ('namespace Symfony\Component\Console\Command; class Command {
        public const SUCCESS = 0;
        public const FAILURE = 1;
        public function __construct() {}
        protected function setDescription(string $description): static { return $this; }
        protected function addOption(string $name, ?string $shortcut = null, ?int $mode = null, string $description = "", mixed $default = null): static { return $this; }
        public function run(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int {
            if (method_exists($this, "execute")) {
                return $this->execute($input, $output);
            }
            return self::SUCCESS;
        }
    }');
}

// ── Nextcloud-Stubs ──────────────────────────────────────────────────────────

if (!interface_exists('OCP\Http\Client\IClient')) {
    // phpcs:ignore
    eval ('namespace OCP\Http\Client; interface IClient {
        public function get(string $uri, array $options = []);
        public function post(string $uri, array $options = []);
    }');
}

if (!interface_exists('OCP\Http\Client\IResponse')) {
    // phpcs:ignore
    eval ('namespace OCP\Http\Client; interface IResponse {
        public function getBody(): string;
    }');
}

if (!interface_exists('OCP\Http\Client\IClientService')) {
    // phpcs:ignore
    eval ('namespace OCP\Http\Client; interface IClientService {
        public function newClient(): IClient;
    }');
}

if (!interface_exists('OCP\IDBConnection')) {
    // phpcs:ignore
    eval ('namespace OCP; interface IDBConnection {
        public function getQueryBuilder();
    }');
}

if (!interface_exists('OCP\IConfig')) {
    // phpcs:ignore
    eval ('namespace OCP; interface IConfig {
        public function getAppValue(string $app, string $key, string $default = ""): string;
        public function setAppValue(string $app, string $key, string $value): void;
    }');
}

if (!interface_exists('OCP\IRequest')) {
    // phpcs:ignore
    eval ('namespace OCP; interface IRequest {
        public function offsetExists($offset): bool;
        public function getParam(string $key, mixed $default = null): mixed;
        public function getServerHost(): string;
        public function getHeader(string $name): string;
        public function getServerProtocol(): string;
    }');
}

if (!interface_exists('OCP\IUser')) {
    // phpcs:ignore
    eval ('namespace OCP; interface IUser {
        public function getUID(): string;
        public function getDisplayName(): ?string;
        public function setDisplayName(string $displayName): void;
        public function getEMailAddress(): ?string;
        public function setEMailAddress(string $mailAddress): void;
        public function isEnabled(): bool;
    }');
}

if (!interface_exists('OCP\IUserManager')) {
    // phpcs:ignore
    eval ('namespace OCP; interface IUserManager {
        public function get(string $uid): ?IUser;
        public function createUser(string $uid, string $password);
        public function getByEmail(string $email): array;
        public function search(string $pattern, ?int $limit = null, ?int $offset = null): array;
    }');
}

if (!interface_exists('OCP\IGroup')) {
    // phpcs:ignore
    eval ('namespace OCP; interface IGroup {
        public function getGID(): string;
        public function getUsers(): array;
        public function addUser(IUser $user): void;
        public function removeUser(IUser $user): void;
        public function inGroup(IUser $user): bool;
    }');
}

if (!interface_exists('OCP\IGroupManager')) {
    // phpcs:ignore
    eval ('namespace OCP; interface IGroupManager {
        public function groupExists(string $gid): bool;
        public function createGroup(string $gid): ?IGroup;
        public function get(string $gid): ?IGroup;
        public function search(string $search, ?int $limit = null, ?int $offset = null): array;
        public function getUserGroups(IUser $user): array;
        public function getUserGroupIds(IUser $user): array;
    }');
}

if (!interface_exists('OCP\Mail\IMessage')) {
    // phpcs:ignore
    eval ('namespace OCP\Mail; interface IMessage {
        public function setFrom(array $addresses): void;
        public function setTo(array $addresses): void;
        public function setSubject(string $subject): void;
        public function setPlainBody(string $text): void;
    }');
}

if (!interface_exists('OCP\IUserSession')) {
    // phpcs:ignore
    eval ('namespace OCP; interface IUserSession {
        public function getUser(): ?\\OCP\\IUser;
    }');
}

if (!interface_exists('OCP\Files\Node')) {
    // phpcs:ignore
    eval ('namespace OCP\Files; interface Node {
        public function getName(): string;
        public function getId(): int;
        public function getMTime(): int;
        public function getSize(): int|float;
        public function getMimeType(): string;
    }');
}
if (!interface_exists('OCP\Files\Folder')) {
    // phpcs:ignore
    eval ('namespace OCP\Files; interface Folder extends Node {
        public function getDirectoryListing(): array;
        public function nodeExists(string $path): bool;
        public function get(string $path);
        public function newFolder(string $path);
        public function newFile(string $path, $content = null);
    }');
}
if (!interface_exists('OCP\Files\File')) {
    // phpcs:ignore
    eval ('namespace OCP\Files; interface File extends Node {
        public function getContent(): string;
    }');
}
if (!class_exists('OCP\Files\NotFoundException')) {
    // phpcs:ignore
    eval ('namespace OCP\Files; class NotFoundException extends \\RuntimeException {}');
}
if (!interface_exists('OCP\Files\IRootFolder')) {
    // phpcs:ignore
    eval ('namespace OCP\Files; interface IRootFolder {
        public function getUserFolder(string $userId): Folder;
    }');
}

if (!interface_exists('OCP\Mail\IMailer')) {
    // phpcs:ignore
    eval ('namespace OCP\Mail; interface IMailer {
        public function createMessage(): IMessage;
        public function send(IMessage $message): void;
    }');
}

if (!class_exists('OCP\AppFramework\Controller')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework; class Controller {
        public function __construct(protected string $appName, protected \OCP\IRequest $request) {}
    }');
}

if (!class_exists('OCP\AppFramework\App')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework; class App {
        public function __construct(protected string $appName = "", protected array $urlParams = []) {}
    }');
}

if (!interface_exists('OCP\AppFramework\Bootstrap\IBootstrap')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework\Bootstrap; interface IBootstrap {
        public function register(IRegistrationContext $context): void;
        public function boot(IBootContext $context): void;
    }');
}

if (!interface_exists('OCP\AppFramework\Bootstrap\IRegistrationContext')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework\Bootstrap; interface IRegistrationContext {
        public function registerService(string $name, \Closure $closure): void;
    }');
}

if (!interface_exists('OCP\AppFramework\Bootstrap\IBootContext')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework\Bootstrap; interface IBootContext {}');
}

if (!class_exists('OCP\AppFramework\Http')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework; class Http {
        public const STATUS_OK = 200;
        public const STATUS_ACCEPTED = 202;
        public const STATUS_BAD_REQUEST = 400;
        public const STATUS_FORBIDDEN = 403;
        public const STATUS_NOT_FOUND = 404;
        public const STATUS_INTERNAL_SERVER_ERROR = 500;
    }');
}

if (!class_exists('OCP\AppFramework\Http\DataResponse')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework\Http; class DataResponse {
        public function __construct(private mixed $data = null, private int $status = 200) {}
        public function getData(): mixed { return $this->data; }
        public function getStatus(): int { return $this->status; }
    }');
}

if (!interface_exists('OCP\DB\QueryBuilder\IQueryBuilder')) {
    // phpcs:ignore
    eval ('namespace OCP\DB\QueryBuilder; interface IQueryBuilder {
        public const PARAM_INT = 1;
        public const PARAM_STR_ARRAY = 2;
        public const PARAM_BOOL = 3;
    }');
}

if (!class_exists('OCP\AppFramework\Db\DoesNotExistException')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework\Db; class DoesNotExistException extends \Exception {}');
}

if (!class_exists('OCP\AppFramework\Db\Entity')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework\Db; class Entity {
        protected array $types = [];
        protected array $data = [];
        protected function addType(string $field, string $type): void {
            $this->types[$field] = $type;
        }
        public function __call(string $name, array $arguments) {
            if (str_starts_with($name, "set")) {
                $property = lcfirst(substr($name, 3));
                if (property_exists($this, $property)) {
                    $this->$property = $arguments[0] ?? null;
                } else {
                    $this->data[$property] = $arguments[0] ?? null;
                }
                return $this;
            }
            if (str_starts_with($name, "get")) {
                $property = lcfirst(substr($name, 3));
                if (property_exists($this, $property)) {
                    return $this->$property;
                }
                return $this->data[$property] ?? null;
            }
            throw new \BadMethodCallException("Unbekannte Methode: " . $name);
        }
    }');
}

if (!class_exists('OCP\AppFramework\Db\QBMapper')) {
    // phpcs:ignore
    eval ('namespace OCP\AppFramework\Db; class QBMapper {
        public function __construct(protected \OCP\IDBConnection $db, protected string $tableName, protected string $entityClass) {}
        protected function getTableName(): string { return $this->tableName; }
        protected function findEntity($qb) { throw new DoesNotExistException("Nicht gefunden"); }
        protected function findEntities($qb): array { return []; }
        protected function mapRowToEntity(array $row) {
            $entity = new $this->entityClass();
            foreach ($row as $key => $value) {
                $method = "set" . str_replace(" ", "", ucwords(str_replace("_", " ", (string)$key)));
                if (method_exists($entity, "__call") || method_exists($entity, $method)) {
                    $entity->$method($value);
                }
            }
            return $entity;
        }
        public function insert($entity) { return $entity; }
        public function update($entity) { return $entity; }
    }');
}

// ── PSR-4 Autoloader ─────────────────────────────────────────────────────────

// Produktiver Code: lib/
spl_autoload_register(static function (string $class) use ($appDir): void {
    $prefix = 'OCA\\ParliamentWinterthur\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $file = $appDir . '/lib/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// Tests: tests/
spl_autoload_register(static function (string $class) use ($appDir): void {
    $prefix = 'OCA\\ParliamentWinterthur\\Tests\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $file = $appDir . '/tests/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});
