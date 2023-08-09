<?php

namespace JsonRpc;

use ReflectionMethod;

/**
 * Абстрактный контроллер для обработки JsonRpc-запроса
 */
abstract class Controller
{
    /** @var array Параметры запроса (значение params) */
    private $requestParams = [];
    /** @var string Вызываемый метод (значение method) */
    private $requestMethod;
    /** @var int Идентификатор запроса (значение id) */
    private $requestId;

    /**
     * Начальный метод, для определения данных пришедших в запросе
     * Основная точка входа конечной точки. Отсюда происходит вызов методов
     *
     * @return false|string
     */
    public function index()
    {
        $response = ['jsonrpc' => '2.0'];
        $request = json_decode(file_get_contents('php://input'), true);
        try {
            $this->resolveRequest($request);
            if (isset($this->requestId)) {
                $response['id'] = $this->requestId;
            }
            $result = $this->{$this->requestMethod}(...$this->resolveArguments());
            $results = array_merge($response, ['result' => $result]);
        } catch (\RuntimeException $e) {
            $results = array_merge($response, $this->responseError(
                new Exception('Internal error', Exception::INTERNAL_ERROR, $e)
            ));
        }

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Content-type: application/json; charset=utf-8');
        return json_encode($results);
    }

    /**
     * Обработка запроса
     *
     * @param array $data Данные запроса
     *
     * @return void
     * @throws \JsonRpc\Exception
     */
    private function resolveRequest($data)
    {
        try {
            $this->checkRequest($data);
            !empty($data['params']) && $this->requestParams = $data['params'];
            $this->requestMethod = $data['method'];
            $this->requestId = $data['id'];
        } catch (Exception $e) {
            throw new Exception('Invalid request JSON', -32700, $e);
        }
    }

    /**
     * Обработка аргументов и получение списка для вызова запрошенного метода
     * Если аргументы метода заданы как DTO формирует эти объекты
     *
     * @return array Список аргументов в порядке следования в сигнатуре метода.
     *
     * @throws \ReflectionException
     * @throws \JsonRpc\Exception
     */
    protected function resolveArguments()
    {
        $reflection = new ReflectionMethod($this, $this->requestMethod);
        $args = [];
        $methodParams = $reflection->getParameters();
        $requestParams = $this->requestParams;
        $onlyOneParam = $reflection->getNumberOfParameters() === 1;
        if ($onlyOneParam) {
            $requestParams = [$methodParams[0]->getName() => $requestParams[$methodParams[0]->getName()]];
        }

        /** обрабатываем список параметров */
        foreach ($methodParams as $param) {
            if (array_key_exists($param->name, $requestParams)) {
                $args[] = $requestParams[$param->name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new Exception('Invalid request params', Exception::INVALID_PARAMS);
            }
        }

        return $args;
    }

    /**
     * Проверяет запроса на корректность
     *
     * @param mixed $data Данные запроса
     *
     * @return void
     * @throws \JsonRpc\Exception
     */
    public function checkRequest($data)
    {
        // проверяем общую структуру
        if (empty($data) || !is_array($data)
            || count(array_diff(array_keys($data), ['jsonrpc', 'id', 'method', 'params']))
            || $data['jsonrpc'] !== '2.0'
        ) {
            throw new Exception('Invalid request', Exception::INVALID_REQUEST);
        }
        // проверяем метод
        if (!method_exists($this, $data['method'])) {
            throw new Exception('Method not found', Exception::METHOD_NOT_FOUND);
        }
    }

    /**
     * Получение отладочной информации
     *
     * @param Exception $e Исключение вызвавшее ошибку
     *
     * @return array
     */
    private function getDebugData(\Exception $e)
    {
        if (getenv('ENVIRONMENT') !== 'development') {
            return [];
        }

        // В окружении разработки (dev) выводим данные по предыдущему Exception
        $data = [];
        $previousException = $e->getPrevious();
        if ($previousException) {
            $data['previousException'] = [
                'class' => get_class($previousException),
                'code' => $previousException->getCode(),
                'message' => $previousException->getMessage(),
                'file' => $previousException->getFile(),
                'line' => $previousException->getLine(),
            ];
        }

        return $data;
    }

    /**
     * Формирование ошибочного ответа
     *
     * @param \Exception $e Исключение с данными по ошибке
     *
     * @return array
     */
    private function responseError(\Exception $e)
    {
        $error = [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
        ];

        $debugData = $this->getDebugData($e);
        if ($debugData) {
            $error['data'] = $debugData;
        }

        return ['error' => $error];
    }
}