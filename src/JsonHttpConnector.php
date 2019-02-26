<?php

namespace mortalswat\JsonHttpConnector;


use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

/**
 * Class JsonHttpConnector
 * @package Domain\JsonHttpConnector
 */
class JsonHttpConnector
{
    /** @var string|null */
    private $logFile;
    /** @var OAuth2|null */
    private $oAuth2;
    /** @var int */
    private $maxRetries;
    /** @var int */
    private $retrySleep;
    /** @var int */
    private $timeout;

    /**
     * JsonHttpConnector constructor.
     * @param null $logFile
     * @param OAuth2|null $oAuth2
     * @param int $maxRetries
     * @param int $retrySleep
     * @param int $timeout
     */
    public function __construct($logFile = null, OAuth2 $oAuth2 = null, $maxRetries = 1, $retrySleep = 1, $timeout = 30)
    {
        $this->logFile = $logFile;
        $this->oAuth2 = $oAuth2;
        $this->maxRetries = $maxRetries;
        $this->retrySleep = $retrySleep;
        $this->timeout = $timeout;
    }

    /**
     * @param Request $request
     * @return JsonHttpResponse
     * @throws JsonHttpConnectorException
     */
    public function execute(Request $request)
    {
        $retry = 0;
        $authorized = false;
        $errors = [];

        do {
            if($this->logFile !== null) {
                $headers = $request->getHeaders();
                $headersString =implode("\n", array_map(
                    function ($key, $value) {
                        return " - $key: $value[0]";
                    },
                    array_keys($headers), $headers
                ));
                $datetime = (new \DateTime())->format('y-m-d H:i:s.u');
                $content = $request->getBody();
                $text = <<<CONTENT
<< ($datetime) Request >>
  Headers:
$headersString
  Contenido:
$content
           
CONTENT;
                file_put_contents(
                    $this->logFile,
                    $text,
                    FILE_APPEND
                );
            }
            if ($this->oAuth2 !== null) {
                try {
                    if($authorized === false){
                        $this->oAuth2->refreshToken();
                    }
                    $token = $this->oAuth2->getToken();
                } catch(\Exception $exception){
                    throw new JsonHttpConnectorException('No se ha podido refrescar el token.');
                }

                /** @var Request $trait */
                $trait = $request->withHeader('Authorization:', 'Bearer '. $token);
                $request = $trait;
            }

            try {
                return $this->sendRequest($request);
            } catch (\Exception $exception){
                if($this->logFile !== null) {
                    file_put_contents(
                        $this->logFile,
                        "-- Ha habido un error --\n" .
                        "Excepción capturada: (" . $exception->getCode() . ") " . $exception->getMessage()."\n\n",
                        FILE_APPEND
                    );
                }
                // Check if is authorized
                $authorized = $exception->getCode() != 401;

                $errors[] = [
                    'code' => $exception->getCode(),
                    'message' => ' ('.$exception->getCode().') '.$exception->getMessage()
                ];
            }

            ++$retry;
        } while(
            $retry < $this->maxRetries &&
            $this->sleepRetry()
        );

        if (count($errors) === 1){
            throw new JsonHttpConnectorException(
                $errors[0]['message'],
                $errors[0]['code']
            );
        }

        throw new JsonHttpConnectorException(
            implode(
                "\n",
                array_map(function ($element){
                    return $element['message'];
                },$errors)
            )
        );
    }

    /**
     * @param Request $request
     * @return JsonHttpResponse
     * @throws JsonHttpConnectorException
     */
    private function sendRequest(Request $request)
    {
        $urlInfo = parse_url($request->getUri());

        $client = new Client(['base_uri' => $urlInfo['host']]);

        $arrayOptions = [];
        if ($this->timeout !== null){
            $arrayOptions['timeout'] = $this->timeout;
        }
        
        try {
            $response = $client->send($request, $arrayOptions);
        } catch (\GuzzleHttp\Exception\GuzzleException $exception) {
            throw new JsonHttpConnectorException(
                'Problema al realizar la petición HTTP: "'.$exception->getMessage().'"',
                $exception->getCode()
            );
        }

        $content = json_decode($response->getBody()->getContents(), true);

        if ($content === null) {
            throw new JsonHttpConnectorException('Error al decodificar json de la respuesta.');
        }

        return new JsonHttpResponse(
            $content,
            $response->getStatusCode()
        );
    }

    /**
     * @return bool
     */
    private function sleepRetry()
    {
        sleep($this->retrySleep);
        return true;
    }
}