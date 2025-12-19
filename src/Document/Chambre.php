<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document]
class Chambre
{
    #[MongoDB\Id]
    private string $id;

    #[MongoDB\Field(type: "string")]
    private ?string $numero = null;

    #[MongoDB\Field(type: "int")]
    private ?int $capacite = null;

    #[MongoDB\Field(type: "float")]
    private ?float $prix = null;

    #[MongoDB\Field(type: "string")]
    private ?string $type = null; // Type de chambre (single, double, etc.)

    #[MongoDB\ReferenceOne(targetDocument: Hotel::class, inversedBy: "chambres")]
    private ?Hotel $hotel = null;
    #[MongoDB\Field(type: "bool")]
    private bool $disponible = true; // Par défaut, la chambre est disponible
#[MongoDB\ReferenceOne(targetDocument: Client::class)]
private ?Client $client = null;



public function getClient(): ?Client
{
    return $this->client;
}

public function setClient(?Client $client): self
{
    $this->client = $client;
    return $this;
}

    public function isDisponible(): bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): self
    {
        $this->disponible = $disponible;
        return $this;
    }
    public function getId(): ?string
    {
        return $this->id;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(string $numero): self
    {
        $this->numero = $numero;
        return $this;
    }

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): self
    {
        $this->capacite = $capacite;
        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(?Hotel $hotel): self
    {
        $this->hotel = $hotel;
        return $this;
    }
}