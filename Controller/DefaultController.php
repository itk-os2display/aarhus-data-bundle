<?php

namespace Itk\AarhusDataBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends Controller
{
    /**
     * Get available functions.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function availableAction()
    {
        return new JsonResponse($this->container->get('itk_aarhus_data.data_service')->getAvailableDataFunctions());
    }

    /**
     * Test function.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function testAction()
    {
        return new JsonResponse($this->container->get('itk_aarhus_data.data_service')->odaaDokk1MeasuresDataFunction());
    }
}
