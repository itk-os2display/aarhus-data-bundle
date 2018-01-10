<?php

namespace Itk\AarhusDataBundle\Service;

use Doctrine\ORM\EntityManager;
use Os2Display\CoreBundle\Events\CronEvent;
use Symfony\Component\Translation\TranslatorInterface;
use GuzzleHttp;
use Doctrine\Common\Cache\CacheProvider;

class DataService
{
    private $entityManager;
    private $translator;
    private $cache;
    private $cacheTTL;

    /**
     * DataService constructor.
     * @param \Doctrine\ORM\EntityManager $entityManager The entity manager.
     * @param \Symfony\Component\Translation\TranslatorInterface $translator The translator.
     * @param \Doctrine\Common\Cache\CacheProvider $cache The cache.
     */
    public function __construct(
        EntityManager $entityManager,
        TranslatorInterface $translator,
        CacheProvider $cache,
        $cacheTTL
    ) {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
        $this->cache = $cache;
        $this->cacheTTL = $cacheTTL;
    }

    /**
     * CronEvent event listener.
     */
    public function onCron()
    {
        $this->processDataFeeds();
    }

    /**
     * Processes the slides with slide_type: itk-aarhus-data.
     */
    public function processDataFeeds()
    {
        $data = null;

        $slides = $this->entityManager
            ->getRepository('Os2DisplayCoreBundle:Slide')
            ->findBySlideType('itk-aarhus-data');

        foreach ($slides as $slide) {
            $options = $slide->getOptions();

            $url = isset($options['data_url']) ? $options['data_url'] : null;
            $type = isset($options['data_type']) ? $options['data_type'] : 'json';

            if (isset($options['data_function'])) {
                $data = $this->dataFunction(
                    $options['data_function'],
                    $url,
                    $type
                );
            } else {
                $data = [];
            }

            // Only set data if it is non-empty
            if (!empty($data)) {
                $slide->setExternalData($data);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @param $url
     * @return null|string
     */
    private function getUrl($url)
    {
        try {
            $client = new GuzzleHttp\Client();
            $res = $client->request(
                'GET',
                $url,
                ['timeout' => 2]
            );

            return $res->getBody()->getContents();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get results from the url. Parse results from json or csv.
     *
     * @param string $url The url from which to get the data.
     * @param string $type The type of the data.
     *   Allowed types: json, csv. Defaults to json.
     *
     * @return array
     */
    private function dataUrl($url, $type = 'json')
    {
        // Serve cached data if available.
        $content = $this->cache->fetch($url);
        if (false === $content) {
            $body = $this->getUrl($url);

            $content = [];

            if (!empty($body)) {
                switch ($type) {
                    case 'json':
                        $content = json_decode($body);
                        break;
                    case 'csv':
                        $lines = explode("\r\n", $body);

                        foreach ($lines as $line) {
                            $content[] = str_getcsv($line);
                        }

                        break;
                }
            }
            $this->cache->save(
                $url,
                $content,
                $this->cacheTTL
            );
        }

        return $content;
    }

    /**
     * Get an array of available data functions.
     *
     * @return array
     */
    public function getAvailableDataFunctions()
    {
        return [
            'data_function.custom' => (object)[
                'id' => 'data_function.custom',
                'label' => $this->translate('data_function.custom'),
                'group' => $this->translate('group.main'),
            ],
            'data_function.ckan' => (object)[
                'id' => 'data_function.ckan',
                'label' => $this->translate('data_function.ckan'),
                'group' => $this->translate('group.main'),
            ],
        ];
    }

    /**
     * Calls the relevant feed function.
     *
     * @param string $functionName The name of the data function to run on the url.
     * @param string $url The url to get.
     * @param string $type The type of the response.
     * @return array
     */
    private function dataFunction($functionName, $url = '', $type = 'json')
    {
        $data = [];

        switch ($functionName) {
            case 'data_function.custom':
                $data = $this->dataUrl($url, $type);
                break;
            case 'data_function.ckan':
                $data = $this->getCKAN($url, $type);
                break;
        }

        return $data;
    }

    /**
     * Translate the key.
     *
     * @param $key
     * @return string
     */
    private function translate($key)
    {
        return $this->translator->trans($key, [], 'ItkAarhusDataBundle');
    }

    /**
     * Get CKAN records.
     *
     * @param $url
     * @return array
     */
    public function getCKAN($url, $type)
    {
        $data = $this->dataUrl($url, $type);

        $result = [];

        if ($data->success && isset($data->result) && isset($data->result->records)) {
            foreach ($data->result->records as $record) {
                array_push(
                    $result,
                    [
                        'name' => $this->translate('field.' . $record->type),
                        'unit' => $this->translate('unit.' . $record->type),
                        'location' => $this->translate('location.waterfront'),
                        'timestamp' => $record->time,
                        'value' => round($record->value),
                    ]
                );
            }
        }


        return $result;
    }
}
