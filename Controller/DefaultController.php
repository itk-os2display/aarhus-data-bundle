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
        return new JsonResponse($this->container->get('itk_aarhus_data.data_service')->getCKAN(
            'https://test.opendata.dk/api/action/datastore_search_sql?sql=SELECT%20*%20from%20%22e4d52a10-35f4-47be-a09d-8e6ab54d9e7e%22%20WHERE%20sensor%20LIKE%20%270004A30B001E8EA2%27%20and%20(type%20=%20%27lux%27%20or%20type%20=%20%27air_temperature%27)%20order%20by%20_id%20desc%20limit%202',
            'json'
        ));
    }
}
