<?php

namespace App\Domain\Identity\Entities;

use App\Domain\Identity\ValueObjects\Email;
use App\Domain\Identity\ValueObjects\PasswordHash;
use App\Domain\Identity\ValueObjects\UserId;
use App\Domain\Identity\ValueObjects\UserStatus;

class User
{
    private UserId $id;
    private string $name;
    private Email $email;
    private PasswordHash $password;
    private UserStatus $status;
    private ?\DateTimeImmutable $emailVerifiedAt;

    private function __construct(
        string $name,
        Email $email,
        PasswordHash $password
    ) {
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->status = UserStatus::PENDING;
        $this->emailVerifiedAt = null;
    }
    public static function hydrate(
        UserId $id,
        string $name,
        Email $email,
        PasswordHash $password,
        UserStatus $status,
        ?\DateTimeImmutable $emailVerifiedAt
    ): self {
        $user = new self($name, $email, $password);
        $user->id = $id;
        $user->status = $status;
        $user->emailVerifiedAt = $emailVerifiedAt;

        return $user;
    }

    public static function register(
        string $name,
        Email $email,
        PasswordHash $password
    ): self {
        return new self($name, $email, $password);
    }

    public function verifyEmail(): void
    {
        $this->emailVerifiedAt = new \DateTimeImmutable();
        $this->status = UserStatus::ACTIVE;
    }

    public function changePassword(PasswordHash $newPassword): void
    {
        $this->password = $newPassword;
    }

    public function canAuthenticate(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    public function verifyPassword(string $plain): bool
    {
        return $this->password->verify($plain);
    }

    public function block(): void
    {
        $this->status = UserStatus::BLOCKED;
    }

    // getters
    public function id(): UserId { return $this->id; }
    public function name(): string { return $this->name; }
    public function email(): Email { return $this->email; }
    public function password(): PasswordHash { return $this->password; }
    public function status(): UserStatus { return $this->status; }
    public function emailVerifiedAt(): ?\DateTimeImmutable { return $this->emailVerifiedAt; }
    public function assignId(UserId $id): void
    {
        if ($this->id !== null) {
            throw new \LogicException('ID already assigned');
        }

        $this->id = $id;
    }
}
