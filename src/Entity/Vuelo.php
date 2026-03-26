<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use App\Repository\VueloRepository;

#[ORM\Entity(repositoryClass: VueloRepository::class)]
class Vuelo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $numero = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $horaSalida = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $horaLlegada = null;

    #[ORM\ManyToOne(targetEntity: Estado::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Estado $estadoActual = null;

    public function getId(): ?int
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

    public function getHoraSalida(): ?\DateTimeInterface
    {
        return $this->horaSalida;
    }

    public function setHoraSalida(?\DateTimeInterface $horaSalida): self
    {
        $this->horaSalida = $horaSalida;
        return $this;
    }

    public function getHoraLlegada(): ?\DateTimeInterface
    {
        return $this->horaLlegada;
    }

    public function setHoraLlegada(?\DateTimeInterface $horaLlegada): self
    {
        $this->horaLlegada = $horaLlegada;
        return $this;
    }

    public function getEstadoActual(): ?Estado
    {
        return $this->estadoActual;
    }

    public function setEstadoActual(?Estado $estadoActual): self
    {
        $this->estadoActual = $estadoActual;
        return $this;
    }
}
