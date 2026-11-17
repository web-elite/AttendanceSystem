<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public readonly int $id;
    public readonly string $name;
    public readonly string $username;
    public readonly string $password_hash;
    public readonly string $role;
    public readonly bool $is_active;
    public readonly string $created_at;
    public readonly string $theme;

    /** Optional HR profile fields (all nullable) */
    public readonly ?string $email;
    public readonly ?string $national_code;
    public readonly ?string $father_name;
    public readonly ?string $birth_date;
    public readonly ?string $birth_city;
    public readonly ?string $id_card_no;
    public readonly ?string $specialty;
    public readonly ?string $job_location;
    public readonly ?string $position;
    public readonly ?int $salary;
    public readonly ?string $notes;

    private function __construct(array $row)
    {
        $this->id            = (int) $row['id'];
        $this->name          = $row['name'];
        $this->username      = $row['username'];
        $this->password_hash = $row['password_hash'];
        $this->role          = $row['role'];
        $this->is_active     = (bool) $row['is_active'];
        $this->created_at    = $row['created_at'];
        $this->theme         = $row['theme'] ?? '';

        $this->email         = $row['email'] ?? null;
        $this->national_code = $row['national_code'] ?? null;
        $this->father_name   = $row['father_name'] ?? null;
        $this->birth_date    = $row['birth_date'] ?? null;
        $this->birth_city    = $row['birth_city'] ?? null;
        $this->id_card_no    = $row['id_card_no'] ?? null;
        $this->specialty     = $row['specialty'] ?? null;
        $this->job_location  = $row['job_location'] ?? null;
        $this->position      = $row['position'] ?? null;
        $this->salary        = isset($row['salary']) && $row['salary'] !== null && $row['salary'] !== ''
            ? (int) $row['salary'] : null;
        $this->notes         = $row['notes'] ?? null;
    }

    public static function find(int $id): ?self
    {
        $stmt = Database::query("SELECT * FROM users WHERE id = ?", [$id]);
        $row  = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    public static function findByUsername(string $username): ?self
    {
        $stmt = Database::query("SELECT * FROM users WHERE username = ?", [$username]);
        $row  = $stmt->fetch();
        return $row ? new self($row) : null;
    }

    /**
     * @return array<int, array>
     */
    public static function all(): array
    {
        $stmt = Database::query("SELECT * FROM users ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public static function create(array $data): void
    {
        // Optional profile fields default to NULL when not provided.
        $data += [
            'email' => null, 'national_code' => null, 'father_name' => null,
            'birth_date' => null, 'birth_city' => null, 'id_card_no' => null,
            'specialty' => null, 'job_location' => null, 'position' => null,
            'salary' => null, 'notes' => null,
        ];

        $stmt = Database::query(
            "INSERT INTO users (name, username, password_hash, role, is_active, created_at,
                                email, national_code, father_name, birth_date, birth_city,
                                id_card_no, specialty, job_location, position, salary, notes)
             VALUES (:name, :username, :password_hash, :role, :is_active, :created_at,
                     :email, :national_code, :father_name, :birth_date, :birth_city,
                     :id_card_no, :specialty, :job_location, :position, :salary, :notes)",
            $data
        );
    }

    /**
     * Updates an existing user's account + profile fields.
     * Null values clear the corresponding profile field.
     */
    public static function update(int $id, array $data): void
    {
        $stmt = Database::query(
            "UPDATE users SET
                name = :name,
                username = :username,
                role = :role,
                is_active = :is_active,
                email = :email,
                national_code = :national_code,
                father_name = :father_name,
                birth_date = :birth_date,
                birth_city = :birth_city,
                id_card_no = :id_card_no,
                specialty = :specialty,
                job_location = :job_location,
                position = :position,
                salary = :salary,
                notes = :notes
             WHERE id = :id",
            array_merge($data, ['id' => $id])
        );
    }

    /**
     * Persists the user's dashboard theme (daisyUI theme name).
     * $theme must already be validated against the whitelist.
     */
    public static function setTheme(int $id, string $theme): void
    {
        Database::query("UPDATE users SET theme = ? WHERE id = ?", [$theme, $id]);
    }

    /**
     * Serializes this model to a plain array the user-form view expects.
     * Password hash is intentionally excluded.
     */
    public function toRow(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'username'   => $this->username,
            'role'       => $this->role,
            'is_active'  => $this->is_active ? 1 : 0,
            'created_at' => $this->created_at,
            'theme'      => $this->theme,
            'email'         => $this->email,
            'national_code' => $this->national_code,
            'father_name'   => $this->father_name,
            'birth_date'    => $this->birth_date,
            'birth_city'    => $this->birth_city,
            'id_card_no'    => $this->id_card_no,
            'specialty'     => $this->specialty,
            'job_location'  => $this->job_location,
            'position'      => $this->position,
            'salary'        => $this->salary,
            'notes'         => $this->notes,
        ];
    }
}
