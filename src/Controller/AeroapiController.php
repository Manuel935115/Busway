<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Vuelo;
use App\Entity\Estado;
use App\Entity\EstadoVuelo;
use App\Entity\Servicio;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class AeroapiController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    private function fetchAeroApi(string $flightNumber, string $apiKey): ?string
    {
        $options = [
            'http' => [
                'method'        => 'GET',
                'header'        => [
                    'x-apikey: ' . trim($apiKey),
                    'Accept: application/json; charset=UTF-8',
                ],
                'ignore_errors' => true,
                'timeout'       => 10,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ];

        $context  = stream_context_create($options);
        $url      = 'https://aeroapi.flightaware.com/aeroapi/flights/' . urlencode($flightNumber);

        set_error_handler(static function () { return true; });
        $body = file_get_contents($url, false, $context);
        restore_error_handler();

        if ($body === false) {
            $lastError = error_get_last();
            $this->logger->error('AeroapiController: file_get_contents falló', [
                'url'   => $url,
                'error' => $lastError['message'] ?? 'desconocido',
            ]);
            return null;
        }

        return $body;
    }

    #[Route('/aeroapi', name: 'app_aeroapi')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $flightNumber = strtoupper(trim($request->query->get('flight', '') ?? ''));
        $flightData   = null;
        $error        = null;

        if ($flightNumber !== '') {
            if (!preg_match('/^[A-Z0-9]{2,3}[0-9]{1,4}[A-Z]?$/', $flightNumber)) {
                $error = "Formato de vuelo no válido. Ejemplos: IB3456, EZY1234, VY1234.";
            } else {
                $apiKey = $_ENV['AERO_API_KEY'] ?? '';

                if (empty($apiKey)) {
                    $error = "Falta configurar la clave AERO_API_KEY en el archivo .env.local de tu proyecto.";
                } else {
                    $fiveMinutesAgo = new \DateTime('-5 minutes');
                    $cachedRecord = $em->getRepository(EstadoVuelo::class)->createQueryBuilder('ev')
                        ->join('ev.vuelo', 'v')
                        ->where('v.numero = :fn')
                        ->andWhere('ev.fechaHora >= :fiveMins')
                        ->setParameter('fn', $flightNumber)
                        ->setParameter('fiveMins', $fiveMinutesAgo)
                        ->orderBy('ev.fechaHora', 'DESC')
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if ($cachedRecord && !empty($cachedRecord->getRawData())) {
                        $bestFlight = $cachedRecord->getRawData();
                        $flightData = ['flights' => [$bestFlight]];
                    } else {
                        $jsonResponse = $this->fetchAeroApi($flightNumber, $apiKey);

                        if ($jsonResponse === null) {
                            $error = "No se pudo contactar con la API de FlightAware.";
                        } else {
                            $flightData = json_decode($jsonResponse, true);

                            if (isset($flightData['detail'])) {
                                $error      = "Error de FlightAware: " . $flightData['detail'];
                                $flightData = null;
                            } elseif (empty($flightData['flights'])) {
                                $error      = "El número de vuelo «{$flightNumber}» no existe o no tiene planes de vuelo activos.";
                                $flightData = null;
                            } else {
                                $bestFlight = null;
                                $now        = time();
                                $minDiff    = PHP_INT_MAX;

                                foreach ($flightData['flights'] as $flight) {
                                    if (stripos($flight['status'] ?? '', 'En Route') !== false) {
                                        $bestFlight = $flight;
                                        break;
                                    }
                                    $scheduledOut = isset($flight['scheduled_out']) ? strtotime($flight['scheduled_out']) : 0;
                                    if ($scheduledOut > 0) {
                                        $diff = abs($now - $scheduledOut);
                                        if ($diff < $minDiff) {
                                            $minDiff    = $diff;
                                            $bestFlight = $flight;
                                        }
                                    }
                                }

                                if (!$bestFlight) {
                                    $bestFlight = $flightData['flights'][0] ?? null;
                                }

                                $flightData['flights'] = [$bestFlight];

                                try {
                                    $statusStr = mb_substr($bestFlight['status'] ?? 'Desconocido', 0, 100);

                                    $estado = $em->getRepository(Estado::class)->findOneBy(['nombre' => $statusStr]);
                                    if (!$estado) {
                                        $estado = new Estado();
                                        $estado->setNombre($statusStr);
                                        $em->persist($estado);
                                    }

                                    $vuelo = $em->getRepository(Vuelo::class)->findOneBy(['numero' => $flightNumber]);
                                    if (!$vuelo) {
                                        $vuelo = new Vuelo();
                                        $vuelo->setNumero($flightNumber);
                                    }

                                    if (!empty($bestFlight['scheduled_out'])) {
                                        $vuelo->setHoraSalidaProgramada(new \DateTime($bestFlight['scheduled_out']));
                                    }
                                    if (!empty($bestFlight['scheduled_in'])) {
                                        $vuelo->setHoraLlegadaProgramada(new \DateTime($bestFlight['scheduled_in']));
                                    }

                                    $actualOut = $bestFlight['actual_out'] ?? $bestFlight['estimated_out'] ?? $bestFlight['scheduled_out'] ?? null;
                                    if ($actualOut) $vuelo->setHoraSalidaReal(new \DateTime($actualOut));

                                    $actualIn = $bestFlight['actual_in'] ?? $bestFlight['estimated_in'] ?? $bestFlight['scheduled_in'] ?? null;
                                    if ($actualIn) $vuelo->setHoraLlegadaReal(new \DateTime($actualIn));

                                    $vuelo->setEstadoActual($estado);
                                    $em->persist($vuelo);

                                    $estadoVuelo = $em->getRepository(EstadoVuelo::class)->findOneBy(['vuelo' => $vuelo]);
                                    if (!$estadoVuelo) {
                                        $estadoVuelo = new EstadoVuelo();
                                        $estadoVuelo->setVuelo($vuelo);
                                    }
                                    $estadoVuelo->setEstado($estado);
                                    $estadoVuelo->setFechaHora(new \DateTime());

                                    if ($actualOut) $estadoVuelo->setHoraSalida(new \DateTime($actualOut));
                                    if ($actualIn)  $estadoVuelo->setHoraLlegada(new \DateTime($actualIn));

                                    $estadoVuelo->setRawData($bestFlight);
                                    $em->persist($estadoVuelo);
                                    $em->persist($vuelo);
                                    $em->flush();
                                } catch (\Exception $e) {
                                    $this->logger->error('AeroapiController::index: error al guardar en BD', [
                                        'flight'    => $flightNumber,
                                        'exception' => $e->getMessage(),
                                    ]);
                                    $error = "Error al guardar en Base de Datos: " . $e->getMessage();
                                }
                            }
                        }
                    }
                }
            }
        }

        $servicios = $em->getRepository(Servicio::class)->createQueryBuilder('s')
            ->where('s.vueloTren NOT LIKE :ave')
            ->andWhere('s.vueloTren != :pendiente')
            ->andWhere('s.vueloTren != :empty')
            ->andWhere('s.codigo != :headerRow')
            ->setParameter('ave', 'AVE%')
            ->setParameter('pendiente', 'Pendiente')
            ->setParameter('empty', '')
            ->setParameter('headerRow', 'codigo')
            ->orderBy('s.fecha', 'ASC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $resp = $this->render('aeroapi/index.html.twig', [
            'controller_name'  => 'AeroapiController',
            'flight_info'      => $flightData,
            'searched_flight'  => $flightNumber,
            'error'            => $error,
            'servicios'        => $servicios,
        ]);
        $resp->setCache(['public' => false, 'max_age' => 60]);
        return $resp;
    }

    #[Route('/api/vuelo/{flight}', name: 'api_vuelo_info', requirements: ['flight' => '.+'])]
    public function apiVuelo(string $flight, EntityManagerInterface $em): JsonResponse
    {
        $flight = strtoupper(trim($flight));
        if (!preg_match('/^[A-Z0-9]{2,3}[0-9]{1,4}[A-Z]?$/', $flight)) {
            return new JsonResponse(['error' => 'Número de vuelo no válido'], 400);
        }
        $apiKey = $_ENV['AERO_API_KEY'] ?? '';
        if (empty($apiKey)) {
            return new JsonResponse(['error' => 'Sin clave API'], 500);
        }
        $fiveMinutesAgo = new \DateTime('-5 minutes');
        $cached = $em->getRepository(EstadoVuelo::class)->createQueryBuilder('ev')
            ->join('ev.vuelo','v')->select('ev.rawData')
            ->where('v.numero=:fn')->andWhere('ev.fechaHora>=:t')
            ->setParameter('fn',$flight)->setParameter('t',$fiveMinutesAgo)->orderBy('ev.fechaHora','DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if($cached&&$cached['rawData']){$r=new JsonResponse(['flight'=>$cached['rawData'],'cached'=>true]);$r->setCache(['public'=>true,'max_age'=>300]);return $r;}
        $json=$this->fetchAeroApi($flight,$apiKey);
        if(!$json)return new JsonResponse(['error'=>'API error'],500);
        $data=json_decode($json,true);
        if(isset($data['detail']))return new JsonResponse(['error'=>$data['detail']],400);
        if(empty($data['flights']))return new JsonResponse(['error'=>'Not found'],404);
        $bestFlight=null;$now=time();$minDiff=PHP_INT_MAX;
        foreach($data['flights'] as $f){if(stripos($f['status']??'','En Route')!==false){$bestFlight=$f;break;}$t=isset($f['scheduled_out'])?strtotime($f['scheduled_out']):0;if($t>0&&abs($now-$t)<$minDiff){$minDiff=abs($now-$t);$bestFlight=$f;}}
        if(!$bestFlight)$bestFlight=$data['flights'][0];
        try{$statusStr=mb_substr($bestFlight['status']??'Unknown',0,100);$estado=$em->getRepository(Estado::class)->findOneBy(['nombre'=>$statusStr]);if(!$estado){$estado=new Estado();$estado->setNombre($statusStr);$em->persist($estado);}$vuelo=$em->getRepository(Vuelo::class)->findOneBy(['numero'=>$flight]);if(!$vuelo){$vuelo=new Vuelo();$vuelo->setNumero($flight);}if(!empty($bestFlight['scheduled_out']))$vuelo->setHoraSalidaProgramada(new \DateTime($bestFlight['scheduled_out']));if(!empty($bestFlight['scheduled_in']))$vuelo->setHoraLlegadaProgramada(new \DateTime($bestFlight['scheduled_in']));$vuelo->setEstadoActual($estado);$em->persist($vuelo);$ev=$em->getRepository(EstadoVuelo::class)->findOneBy(['vuelo'=>$vuelo]);if(!$ev){$ev=new EstadoVuelo();$ev->setVuelo($vuelo);}$ev->setEstado($estado);$ev->setFechaHora(new \DateTime());$ev->setRawData($bestFlight);$em->persist($ev);$em->flush();}catch(\Throwable $e){$this->logger->error('AeroapiController::apiVuelo: error',['flight'=>$flight,'exception'=>$e->getMessage()]);}
        $r=new JsonResponse(['flight'=>$bestFlight,'cached'=>false]);$r->setCache(['public'=>true,'max_age'=>300]);return $r;
    }

    #[Route('/historial', name: 'app_aeroapi_historial')]
    public function historial(EntityManagerInterface $em): Response
    {
        $records = $em->getRepository(EstadoVuelo::class)->findBy(
            [],
            ['fechaHora' => 'DESC'],
            10,
            0
        );

        return $this->render('aeroapi/historial.html.twig', [
            'records' => $records,
        ]);
    }

    #[Route('/api/historial', name: 'api_historial', methods: ['GET'])]
    public function apiHistorial(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $o=(int)$request->query->get('offset',0);
        $l=min((int)$request->query->get('limit',20),100);
        $r=$em->getRepository(EstadoVuelo::class)->createQueryBuilder('e')
            ->select('e.id','e.rawData','e.fechaHora','e.horaSalida','s.nombre','v.numero')
            ->join('e.vuelo','v')
            ->join('e.estado','s')
            ->where('s.nombre IS NOT NULL')
            ->orderBy('e.fechaHora','DESC')
            ->setMaxResults($l)
            ->setFirstResult($o)
            ->getQuery()
            ->setFetchMode(EstadoVuelo::class, 'vuelo', \Doctrine\ORM\Query::FETCH_EAGER)
            ->getResult();
        $d=[];foreach($r as $x){$w=$x['rawData'];$d[]=['id'=>$x['id'],'v'=>$x['numero'],'oi'=>$w['operator_icao']??'','oia'=>$w['operator_iata']??'','o'=>$w['origin']['code_iata']??$w['origin']['code']??'—','d'=>$w['destination']['code_iata']??$w['destination']['code']??'—','hs'=>$x['horaSalida']?$x['horaSalida']->format('d/m/Y H:i'):null,'s'=>$x['nombre'],'fb'=>$x['fechaHora']->format('d/m/Y H:i:s'),'rw'=>$w];}
        $c=new JsonResponse(['r'=>$d],200,[]);
        $c->setCache(['public'=>true,'max_age'=>300]);
        return $c;
    }

    #[Route('/api/servicios', name: 'api_servicios', methods: ['GET'])]
    public function apiServicios(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $o=max(0,(int)$request->query->get('offset',0));
        $l=min((int)$request->query->get('limit',20),100);
        $r=$em->getRepository(Servicio::class)->createQueryBuilder('s')
            ->where('s.vueloTren NOT LIKE :ave')->andWhere('s.vueloTren!=:p')->andWhere('s.vueloTren!=:e')->andWhere('s.codigo!=:h')
            ->setParameter('ave','AVE%')->setParameter('p','Pendiente')->setParameter('e','')->setParameter('h','codigo')
            ->orderBy('s.fecha','ASC')->setFirstResult($o)->setMaxResults($l)->getQuery()->getResult();
        $d=[];foreach($r as $x){$d[]=['co'=>$x->getCodigo(),'or'=>$x->getOrigen(),'de'=>$x->getDestino(),'pa'=>$x->getPasajeros(),'fe'=>$x->getFecha(),'vt'=>$x->getVueloTren(),'cd'=>$x->getCoord()];}
        $resp=new JsonResponse(['s'=>$d]);$resp->setCache(['public'=>true,'max_age'=>120]);return $resp;
    }

    #[Route('/historial/eliminar/{id}', name: 'app_aeroapi_eliminar', methods: ['POST'])]
    public function eliminar(int $id, EntityManagerInterface $em): Response
    {
        $estadoVuelo = $em->getRepository(EstadoVuelo::class)->find($id);
        if ($estadoVuelo) {
            $vuelo = $estadoVuelo->getVuelo();
            $em->remove($estadoVuelo);
            if($vuelo && $em->getRepository(EstadoVuelo::class)->count(['vuelo'=>$vuelo])===0){$em->remove($vuelo);}
            $em->flush();
            $this->addFlash('success', 'Registro eliminado correctamente.');
        }
        return $this->redirectToRoute('app_aeroapi_historial');
    }
}
