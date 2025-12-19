<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

#[MongoDB\Document]
class Hotel
{
    #[MongoDB\Id]
    private string $id;

    #[MongoDB\Field(type: "string")]
    private ?string $nom = null;

    #[MongoDB\Field(type: "string")]
    private ?string $adresse = null;

    #[MongoDB\Field(type: "string")]
    private ?string $ville = null;

    #[MongoDB\Field(type: "string")]
    private ?string $telephone = null;

    #[MongoDB\Field(type: "int")]
    private int $categorie;  // Catégorie de l'hôtel (*, **, ***)

    #[MongoDB\ReferenceMany(targetDocument: Chambre::class, mappedBy: "hotel", cascade: ["persist", "remove"])]
    private iterable $chambres = [];

    #[MongoDB\Field(type: "date", nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[MongoDB\Field(type: "string", nullable: true)]
    private ?string $updatedBy = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getCategorie(): int
    {
        return $this->categorie;
    }

    public function setCategorie(int $categorie): self
    {
        if ($categorie < 1 || $categorie > 5) {
            throw new \InvalidArgumentException('La catégorie doit être comprise entre 1 et 5.');
        }
        $this->categorie = $categorie;
        return $this;
    }

    public function getChambres(): iterable
    {
        return $this->chambres;
    }

    public function addChambre(Chambre $chambre): self
    {
        $this->chambres[] = $chambre;
        return $this;
    }

    public function removeChambre(Chambre $chambre): self
    {
        $this->chambres = array_filter($this->chambres, fn($c) => $c !== $chambre);
        return $this;
    }
    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
