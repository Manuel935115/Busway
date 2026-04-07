<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\TrenRepository;

#[ORM\Entity(repositoryClass: TrenRepository::class)]
class Tren
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $codigoComercial = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $tipo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCodigoComercial(): ?string
    {
        return $this->codigoComercial;
    }

    public function setCodigoComercial(string $codigoComercial): self
    {
        $this->codigoComercial = $codigoComercial;
        return $this;
    }

    public function getTipo(): ?string
    {
        return $this->tipo;
    }

    public function setTipo(?string $tipo): self
    {
        $this->tipo = $tipo;
        return $this;
    }
}
