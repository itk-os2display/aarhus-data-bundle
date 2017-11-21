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
        return [
            'data_function.odaa-dokk1.all' => (object)[
                'id' => 'data_function.odaa-dokk1.all',
                'label' => $this->translate('data_function.odaa-dokk1.all'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.temperature' => (object)[
                'id' => 'data_function.odaa-dokk1.temperature',
                'label' => $this->translate('data_function.odaa-dokk1.temperature'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.daylight' => (object)[
                'id' => 'data_function.odaa-dokk1.daylight',
                'label' => $this->translate('data_function.odaa-dokk1.daylight'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.sound' => (object)[
                'id' => 'data_function.odaa-dokk1.sound',
                'label' => $this->translate('data_function.odaa-dokk1.sound'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.humidity' => (object)[
                'id' => 'data_function.odaa-dokk1.humidity',
                'label' => $this->translate('data_function.odaa-dokk1.humidity'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
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
            case "data_function.odaa-dokk1.all":
                $data = $this->odaaDokk1MeasuresDataFunction(null);
                break;
            case "data_function.odaa-dokk1.temperature":
                $data = $this->odaaDokk1MeasuresDataFunction("temperature");
                break;
            case "data_function.odaa-dokk1.daylight":
                $data = $this->odaaDokk1MeasuresDataFunction("daylight");
                break;
            case "data_function.odaa-dokk1.sound":
                $data = $this->odaaDokk1MeasuresDataFunction("sound");
                break;
            case "data_function.odaa-dokk1.humidity":
                $data = $this->odaaDokk1MeasuresDataFunction("humidity");
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
    public function odaaDokk1MeasuresDataFunction($field)
    {
        $input = null;

        try {
            $url = 'http://www.odaa.dk/api/3/action/datastore_search?resource_id=e123e70c-9d13-461e-8715-f06ec41dd3cf';
            $input = json_decode(file_get_contents($url));
        } catch (\Exception $e) {
            return null;
        }

        if ($input === false || !isset($input->result) || !isset($input->result->records)) {
            return null;
        }

        $data = [];

        $extractValues = [
            'temperature' => 'TCA',
            'daylight' => 'LUM',
            'sound' => 'MCP',
            'humidity' => 'HUMA'
        ];

        foreach ($extractValues as $key => $value) {
            if ($field == null || $field == $key) {
                $item = array_filter($input->result->records, function ($item) use (&$value) {
                    return $item->sensor == $value;
                });

                if (empty($item)) {
                    continue;
                }

                $item = reset($item);

                array_push($data, [
                    'name' => $this->translate('field.' . $key),
                    'unit' => $this->translate('unit.' . $key),
                    'location' => $this->translate('location.odaa-dokk1'),
                    'timestamp' => $item->time,
                    'value' => round($item->val),
                ]);
            }
        }

        return $data;
    }

}
