<?php

namespace Enoc\Login\models;

class Users {

    private int $id = 0;
    private string $email = "";
    private string $password_hash = "";
    private string $name = "";  // ← NUEVO: Campo para nombre

    private string $role = 'user'; // ← NUEVO: Campo para rol

    public function __construct() {}

    public function set(string $name, $value): void {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    public function get(string $name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }

    public function getId(): int {
        return $this->id;
    }

    public function setId(int $id): void {
        $this->id = $id;
    }

    public function getEmail(): string {
        return $this->email;
    }

    public function setEmail(string $email): void {
        $this->email = $email;
    }

    public function getPassword(): string {
        return $this->password_hash;
    }

    public function setPassword(string $password): void {
        $this->password_hash = $password;
    }

    // ← NUEVOS: Getter y setter para name
    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
    }

    public function getRole(): string {
        return $this->role;
    }
    public function setRole(string $role): void {
        $this->role = $role;
    }
}