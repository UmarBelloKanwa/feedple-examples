<?php

namespace App\Controller;

use App\Service\FeedpleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(FeedpleService $feedpleService): Response
    {
        $widgetPublicKey = $_ENV['FEEDPLE_WIDGET_PUBLIC_KEY'] ?? 'wpk_demo_public_key';

        return $this->render('home/index.html.twig', [
            'widget_public_key' => $widgetPublicKey,
            'sdk_active' => $feedpleService->isSdkActive(),
        ]);
    }
}
