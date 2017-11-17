<?php

namespace Itk\AarhusDataBundle\Service;

use Doctrine\ORM\EntityManager;
use Os2Display\CoreBundle\Events\CronEvent;
use Symfony\Component\Translation\TranslatorInterface;

class DataService
{
    private $entityManager;
    private $translator;

    /**
     * DataService constructor.
     */
    public function __construct(
        EntityManager $entityManager,
        TranslatorInterface $translator
    ) {
        $this->entityManager = $entityManager;
        $this->translator = $translator;
    }

    /**
     * CronEvent event listener.
     *
     * @param CronEvent $event
     */
    public function onCron(CronEvent $event)
    {
        $this->processDataFeeds();
    }

    /**
     * Processes the slides with slide_type: itk-aarhus-data.
     */
    public function processDataFeeds()
    {
        $cache = [];

        $data = null;

        $slides = $this->entityManager
            ->getRepository('Os2DisplayCoreBundle:Slide')
            ->findBySlideType('itk-aarhus-data');

        foreach ($slides as $slide) {
            $options = $slide->getOptions();

            if (isset($options['data_function'])) {
                if (isset($cache[$options['data_function']])) {
                    $data = $cache[$options['data_function']];
                } else {
                    $data = $this->dataFunction($options['data_function']);
                    $cache[$options['data_function']] = $data;
                }
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
     * Get an array of available data functions.
     *
     * @return array
     */
    public function getAvailableDataFunctions()
    {
        // @TODO: Get translation.

        return [
            'odaa-dokk1' => $this->translate('data_function.odaa-dokk1'),
        ];
    }

    /**
     * Calls the relevant feed function.
     *
     * @param $functionName
     * @return array|null
     */
    private function dataFunction($functionName)
    {
        $data = [];

        switch ($functionName) {
            case "odaa-dokk1":
                $data = $this->odaaDokk1MeasuresDataFunction();
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
     * Gets temperature, daylight, volume, humidity from Dokk1
     * http://www.odaa.dk/api/3/action/datastore_search?resource_id=e123e70c-9d13-461e-8715-f06ec41dd3cf
     *
     * @return array|null
     */
    public function odaaDokk1MeasuresDataFunction()
    {
        $input = null;

        try {
            $url = 'http://www.odaa.dk/api/3/action/datastore_search?resource_id=e123e70c-9d13-461e-8715-f06ec41dd3cf';
            $input = json_decode(file_get_contents($url));
        } catch (\Exception $e) {
            return null;
        }

        if ($input === false) {
            return null;
        }

        $data = [];

        $data[0] = [
            'name' => $this->translate('field.temperature'),
            'unit' => '°C',
            'value' => round($input->result->records[0]->val),
//            'value_suffix' => '°',
        ];

        $data[1] = [
            'name' => $this->translate('field.daylight'),
            'unit' => 'Lux',
            'value' => round($input->result->records[2]->val),
        ];

        $data[2] = [
            'name' => $this->translate('field.sound'),
            'unit' => 'dB',
            'value' => round($input->result->records[3]->val),
        ];

        $data[3] = [
            'name' => $this->translate('field.humidity'),
            'unit' => '%',
            'value' => round($input->result->records[1]->val),
        ];

        return $data;
    }

}
