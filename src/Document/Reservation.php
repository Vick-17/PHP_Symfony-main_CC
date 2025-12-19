<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use App\Document\Hotel;

#[MongoDB\Document(collection: "reservation")]
class Reservation
{
    #[MongoDB\Id]
    private string $id;

    #[MongoDB\Field(type: "string")]
    private ?string $numeroReservation = null;

    #[MongoDB\ReferenceOne(targetDocument: Hotel::class)]
    private ?Hotel $hotel = null;

    #[MongoDB\ReferenceMany(targetDocument: Chambre::class)]
    private iterable $chambres = [];

    #[MongoDB\ReferenceOne(targetDocument: Client::class, inversedBy: 'reservations', nullable: true)]
    private ?Client $client = null;

    #[MongoDB\Field(type: "date")]
    private ?\DateTime $dateDebut = null;

    #[MongoDB\Field(type: "date")]
    private ?\DateTime $dateFin = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getNumeroReservation(): ?string
    {
        return $this->numeroReservation;
    }

    public function setNumeroReservation(string $numeroReservation): self
    {
        $this->numeroReservation = $numeroReservation;
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

    /**
     * @return iterable<Chambre>
     */
    public function getChambres(): iterable
    {
        return $this->chambres;
    }

    public function addChambre(Chambre $chambre): self
    {
        $this->chambres[] = $chambre;
        return $this;
    }

    public function setChambres(iterable $chambres): self
    {
        $this->chambres = $chambres;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTime $dateDebut): self
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTime $dateFin): self
    {
        $this->dateFin = $dateFin;
        return $this;
    }
}
