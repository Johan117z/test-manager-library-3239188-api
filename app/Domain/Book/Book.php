<?php

namespace App\Domain\Book;

use App\Domain\Shared\BusinessRuleViolation;
use DateTimeImmutable;

/**
 * Aggregate root of the catalog. Invariants: 0 <= available <= total, total >= 1, title not empty (RN-1, CA-03.3).
 * Changes to available copies are tracked as a delta so the repository can apply them atomically
 * (available_copies = available_copies + delta), with the CHECK constraint as the last barrier.
 */
final class Book
{
    private int $loadedAvailable;

    public function __construct(
        private readonly string $id,
        private string $title,
        private string $author,
        private readonly Isbn $isbn,
        private string $categoryId,
        private int $totalCopies,
        private int $availableCopies,
        private ?string $coverKey = null,
        private ?DateTimeImmutable $deletedAt = null,
    ) {
        $this->loadedAvailable = $availableCopies;
    }

    /** CA-03.1: available copies start equal to the total. */
    public static function create(string $id, string $title, string $author, Isbn $isbn, string $categoryId, int $totalCopies): self
    {
        self::assertTotal($totalCopies);

        return new self($id, self::text($title, 'title', 250), self::text($author, 'author', 200), $isbn, $categoryId, $totalCopies, $totalCopies);
    }

    /** CA-03.3: changing the total moves available copies by the same difference, never below 0. */
    public function update(string $title, string $author, string $categoryId, int $totalCopies): void
    {
        self::assertTotal($totalCopies);
        $newAvailable = $this->availableCopies + ($totalCopies - $this->totalCopies);
        if ($newAvailable < 0) {
            $lent = $this->totalCopies - $this->availableCopies;
            throw BusinessRuleViolation::validation('total_copies', "no puede ser menor que los ejemplares prestados ({$lent})");
        }

        $this->title = self::text($title, 'title', 250);
        $this->author = self::text($author, 'author', 200);
        $this->categoryId = $categoryId;
        $this->totalCopies = $totalCopies;
        $this->availableCopies = $newAvailable;
    }

    /** Lend one copy (used by loans). */
    public function withdraw(): void
    {
        if ($this->availableCopies < 1) {
            throw new BusinessRuleViolation('BOOK_NOT_AVAILABLE', "No quedan ejemplares disponibles de «{$this->title}».");
        }
        $this->availableCopies--;
    }

    /** Return one copy (used by returns). Never above the total (CA-07.3). */
    public function restock(): void
    {
        if ($this->availableCopies >= $this->totalCopies) {
            throw new \LogicException('Available copies cannot exceed the total.');
        }
        $this->availableCopies++;
    }

    /** CA-03.5: logical delete. */
    public function softDelete(DateTimeImmutable $at): void
    {
        $this->deletedAt = $at;
    }

    public function setCover(string $key): void
    {
        if ($key === '') {
            throw new \InvalidArgumentException('Cover key cannot be empty.');
        }
        $this->coverKey = $key;
    }

    public function removeCover(): void
    {
        $this->coverKey = null; // null, never an empty string (CA-03.4)
    }

    public function id(): string { return $this->id; }
    public function title(): string { return $this->title; }
    public function author(): string { return $this->author; }
    public function isbn(): Isbn { return $this->isbn; }
    public function categoryId(): string { return $this->categoryId; }
    public function totalCopies(): int { return $this->totalCopies; }
    public function availableCopies(): int { return $this->availableCopies; }
    public function coverKey(): ?string { return $this->coverKey; }
    public function deletedAt(): ?DateTimeImmutable { return $this->deletedAt; }
    public function isActive(): bool { return $this->deletedAt === null; }

    /** Change of available copies since the book was loaded. */
    public function availableDelta(): int
    {
        return $this->availableCopies - $this->loadedAvailable;
    }

    private static function assertTotal(int $total): void
    {
        if ($total < 1) {
            throw BusinessRuleViolation::validation('total_copies', 'debe ser al menos 1');
        }
    }

    private static function text(string $value, string $field, int $max): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $max) {
            throw BusinessRuleViolation::validation($field, "obligatorio, máximo {$max} caracteres");
        }

        return $value;
    }
}
