<?php

namespace App\baseClasses;

use Symfony\Component\HttpFoundation\Request as HttpRequest;

class KCRequest extends HttpRequest {

	public function __construct() {
		parent::__construct(
			$_GET,
			$_POST,
			[],
			$_COOKIE,
			$_FILES,
			$_SERVER
		);
	}

	public function getInputs() {

        if (!empty($this->headers->get('content-type')) && (strpos($this->headers->get('content-type'), 'multipart/form-data') !== false ||
            strpos($this->headers->get('content-type'), 'application/x-www-form-urlencoded') !== false )) {
           
            //sanitize request data  recursively
            $requestArr = kcRecursiveSanitizeTextField(collect($this->request)->toArray());
            $filesArr = collect($this->files)->toArray();

            $parameters = collect([])->merge($requestArr);
            $parameters = $parameters->merge($filesArr);

            $parameters = $parameters->toArray();
        } else {
            if ( $this->getContent() ) {
                $parameters = json_decode( $this->getContent(), true );
            } else {

                $parameters = $this->query;
            }

            //sanitize request data  recursively
            $parameters = kcRecursiveSanitizeTextField( $parameters );
        }

		return $parameters;
	}
}