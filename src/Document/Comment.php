<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use App\Document\Reservation;

#[MongoDB\Document(collection: "comment")]
class Comment
{
    #[MongoDB\Id]
    private string $id;

    #[MongoDB\Field(type: "string")]
    private string $content;

    #[MongoDB\Field(type: "date")]
    private \DateTime $createdAt;

    #[MongoDB\ReferenceOne(targetDocument: Reservation::class)]
    private ?Reservation $reservation = null;

    #[MongoDB\ReferenceOne(targetDocument: Client::class)]
    private ?Client $author = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }
    

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(?Reservation $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function getAuthor(): ?Client
    {
        return $this->author;
    }

    public function setAuthor(?Client $author): self
    {
        $this->author = $author;
        return $this;
    }
}
