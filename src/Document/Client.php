<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[MongoDB\Document]
class Client implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[MongoDB\Id]
    private string $id;

    #[MongoDB\Field(type: "string")]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    private ?string $nom = null;

    #[MongoDB\Field(type: "string")]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'email n\'est pas valide.')]
    private ?string $email = null;

    #[MongoDB\Field(type: "string")]
    #[Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire.')]
    private ?string $telephone = null;

    #[MongoDB\Field(type: "string")]
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    private ?string $password = null;

    #[MongoDB\Field(type: "collection")]
    private array $roles = ['ROLE_USER'];
    #[MongoDB\Field(type: "string", nullable: true)]
    private ?string $securityQuestion = null;

    #[MongoDB\Field(type: "string", nullable: true)]
    private ?string $securityAnswer = null;
    #[MongoDB\Field(type: "string", nullable: true)]
    private ?string $resetCode = null;


#[MongoDB\Field(type: "int", nullable: true)]
private ?int $autoIncrementId = null;

#[MongoDB\Field(type: "date", nullable: true)]
private ?\DateTimeInterface $resetRequestedAt = null;

public function getResetRequestedAt(): ?\DateTimeInterface
{
    return $this->resetRequestedAt;
}
public function setResetRequestedAt(?\DateTimeInterface $date): self
{
    $this->resetRequestedAt = $date;
    return $this;
}


    public function getAutoIncrementId(): ?int
    {
        return $this->autoIncrementId;
    }

    public function setAutoIncrementId(int $autoIncrementId): self
    {
        $this->autoIncrementId = $autoIncrementId;
        return $this;
    }
    public function getResetCode(): ?string
    {
        return $this->resetCode;
    }
    
    public function setResetCode(?string $resetCode): self
    {
        $this->resetCode = $resetCode;
        return $this;
    }
    private ?string $resetToken = null;

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): self
    {
        $this->resetToken = $resetToken;
        return $this;
    }
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        if (!in_array('ROLE_USER', $roles, true)) {
            $roles[] = 'ROLE_USER';
        }
        return array_values(array_unique($roles));
    }


    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Si vous stockez des données sensibles temporaires, nettoyez-les ici
    }

    public function getUserIdentifier(): string
    {
        return $this->email; // Utilisez l'email comme identifiant unique
    }
}
