<?php

namespace Itk\AarhusDataBundle\Service;

use Doctrine\ORM\EntityManager;
use Os2Display\CoreBundle\Events\CronEvent;
use Symfony\Component\Translation\TranslatorInterface;
use GuzzleHttp;

class DataService
{
    private $entityManager;
    private $translator;

    /**
     * DataService constructor.
     * @param \Doctrine\ORM\EntityManager $entityManager The entity manager.
     * @param \Symfony\Component\Translation\TranslatorInterface $translator The translator.
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
                    $data = $this->dataFunction(
                        $options['data_function'],
                        $options['data_url'],
                        $options['data_type']
                    );
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
     *   Allowed types: json, csv.
     *
     * @return array
     */
    private function dataUrl($url, $type)
    {
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
            'data_function.odaa-dokk1.all' => (object)[
                'id' => 'data_function.odaa-dokk1.all',
                'label' => $this->translate('data_function.odaa-dokk1.all'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.temperature' => (object)[
                'id' => 'data_function.odaa-dokk1.temperature',
                'label' => $this->translate(
                    'data_function.odaa-dokk1.temperature'
                ),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.daylight' => (object)[
                'id' => 'data_function.odaa-dokk1.daylight',
                'label' => $this->translate(
                    'data_function.odaa-dokk1.daylight'
                ),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.sound' => (object)[
                'id' => 'data_function.odaa-dokk1.sound',
                'label' => $this->translate('data_function.odaa-dokk1.sound'),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.odaa-dokk1.humidity' => (object)[
                'id' => 'data_function.odaa-dokk1.humidity',
                'label' => $this->translate(
                    'data_function.odaa-dokk1.humidity'
                ),
                'group' => $this->translate('group.odaa-dokk1'),
            ],
            'data_function.birthday' => (object)[
                'id' => 'data_function.birthday',
                'label' => $this->translate(
                    'data_function.birthday'
                ),
                'group' => $this->translate('group.other'),
            ],
            /*            'data_function.aarhus-library-school-sun-energy' => (object)[
                            'id' => 'data_function.aarhus-library-school-sun-energy',
                            'label' => $this->translate('data_function.aarhus-library-school-sun-energy'),
                            'group' => $this->translate('group.aarhus'),
                        ],*/
        ];
    }

    /**
     * Calls the relevant feed function.
     *
     * @param $functionName
     * @return array|null
     */
    private function dataFunction($functionName, $url = null, $type = 'json')
    {
        $data = [];

        switch ($functionName) {
            case 'data_function.odaa-dokk1.all':
                $data = $this->odaaDokk1MeasuresDataFunction(null, $url, $type);
                break;
            case 'data_function.odaa-dokk1.temperature':
                $data = $this->odaaDokk1MeasuresDataFunction(
                    'temperature',
                    $url,
                    $type
                );
                break;
            case 'data_function.odaa-dokk1.daylight':
                $data = $this->odaaDokk1MeasuresDataFunction(
                    'daylight',
                    $url,
                    $type
                );
                break;
            case 'data_function.odaa-dokk1.sound':
                $data = $this->odaaDokk1MeasuresDataFunction(
                    'sound',
                    $url,
                    $type
                );
                break;
            case 'data_function.odaa-dokk1.humidity':
                $data = $this->odaaDokk1MeasuresDataFunction(
                    'humidity',
                    $url,
                    $type
                );
                break;
            case 'data_function.aarhus-library-school-sun-energy':
                $data = $this->aarhusLibraryAndSchoolSunEnergyProduce(
                    $url,
                    $type
                );
                break;
            case 'data_function.birthday':
                $data = $this->birthday($url, $type);
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
     * Sorts elements from data url by birthday.
     *
     * @param $url
     * @param $type
     * @return array
     */
    public function birthday($url, $type)
    {
        $data = $this->dataUrl($url, $type);

        $currentMonth = date('n');
        $currentDay = date('j');
        $currentYear = date('Y');

        foreach ($data as $dataElement) {
            $name = $dataElement[0];
            $birth = explode('/', $dataElement[1]);
            $day = $birth[0];
            $month = $birth[1];

            if ($month < $currentMonth) {
                $birthday = mktime(23, 59, 0, $month, $day, $currentYear + 1);
            } elseif ($month == $currentMonth && $day > $currentDay) {
                $birthday = mktime(23, 59, 0, $month, $day, $currentYear + 1);
            } else {
                $birthday = mktime(23, 59, 0, $month, $day);
            }
        }

        return $data;
    }

    /**
     * Gets sun energy production from schools and libraries in Aarhus.
     * http://www.odaa.dk/api/3/action/datastore_search?resource_id=251528ca-8ec9-4b70-9960-83c4d0c4e7b6
     *
     * @return array|null
     */
    public function aarhusLibraryAndSchoolSunEnergyProduce($url, $type)
    {
        $data = [];
        $time = null;

        $inputCurrent = $this->dataUrl($url, $type);

        if ($inputCurrent === false ||
            !isset($inputCurrent->result) ||
            !isset($inputCurrent->result->records)) {
            return null;
        }

        $sumCurrent = 0;
        $sumToday = 0;

        foreach ($inputCurrent->result->records as $record) {
            $sumCurrent = $sumCurrent + $record->current;
            $sumToday = $sumToday + $record->daily;
        }

        $data[0] = (object)[
            'name' => $this->translate('field.energy_current'),
            'id' => 'current',
            'value' => floor($sumCurrent / 1000),
            'timestamp' => $time,
            'unit' => $this->translate('unit.energy_current'),
        ];

        $data[1] = (object)[
            'name' => $this->translate('field.energy_today'),
            'id' => 'today',
            'value' => floor($sumToday / 1000),
            'timestamp' => $time,
            'unit' => $this->translate('unit.energy_today'),
        ];

        /*
                $data[2] = (object)[
                    'name' => $this->translate('field.energy_yesterday'),
                    'id' => 'yesterday',
                    'value' => 0,
                    'timestamp' => $time,
                    'unit' => $this->translate('unit.energy_yesterday'),
                ];
        */

        return $data;
    }

    /**
     * Gets temperature, daylight, volume, humidity from Dokk1
     * http://www.odaa.dk/api/3/action/datastore_search?resource_id=e123e70c-9d13-461e-8715-f06ec41dd3cf
     *
     * @return array|null
     */
    public function odaaDokk1MeasuresDataFunction($field, $url, $type)
    {
        $input = $this->dataUrl($url, $type);

        if ($input === false || !isset($input->result) || !isset($input->result->records)) {
            return null;
        }

        $data = [];

        $extractValues = [
            'temperature' => 'TCA',
            'daylight' => 'LUM',
            'sound' => 'MCP',
            'humidity' => 'HUMA',
        ];

        foreach ($extractValues as $key => $value) {
            if ($field == null || $field == $key) {
                $item = array_filter(
                    $input->result->records,
                    function ($item) use (&$value) {
                        return $item->sensor == $value;
                    }
                );

                if (empty($item)) {
                    continue;
                }

                $item = reset($item);

                array_push(
                    $data,
                    [
                        'name' => $this->translate('field.'.$key),
                        'unit' => $this->translate('unit.'.$key),
                        'location' => $this->translate('location.odaa-dokk1'),
                        'timestamp' => $item->time,
                        'value' => round($item->val),
                    ]
                );
            }
        }

        return $data;
    }

}
