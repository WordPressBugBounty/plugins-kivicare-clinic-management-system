<?php

namespace App\baseClasses;

use Exception;

class KCRoutesHandler extends KCBase {

	public  $routes;

	public  $controllerPath;

	public function __construct($controllerPath) {
	    $this->controllerPath = $controllerPath;
    }

    public function init() {

		// Action to handle routes...
		add_action( "wp_ajax_ajax_post", [ $this, 'ajaxPost' ] );
		add_action( "wp_ajax_nopriv_ajax_post", [ $this, 'ajaxPost' ] );
		add_action( "wp_ajax_ajax_get", [ $this, 'ajaxGet' ] );
		add_action( "wp_ajax_nopriv_ajax_get", [ $this, 'ajaxGet' ] );
	}

	public function ajaxPost() {

		$request = new KCRequest();

		$requestData = $request->getInputs();

		try {

            //check if request method is post method
            if ( strtolower( sanitize_textarea_field(wp_unslash($_SERVER['REQUEST_METHOD']) )) !== 'post' ) {
                $error = __('Method is not allowed','kc-lang');
	            wp_send_json(kcThrowExceptionResponse($error, 405 ));
            }

			$this->routes = (new KCRoutes())->routes();
			
            //check if request route key exists in route array
			if ( isset( $this->routes[ $requestData['route_name'] ] ) ) {

                //get request route value from route array
                $route = $this->routes[ $requestData['route_name'] ];

                //check if request route method is same as required method
				if ( strtolower( $route['method'] ) !== 'post' ) {
					$error = __('Method is not allowed','kc-lang');
					wp_send_json(kcThrowExceptionResponse($error, 405 ));
				}

                //check route value have nonce if not set nonce to 100
				if (!isset($route['nonce'])) {
					$route['nonce'] = 1;
				}

				if ( ! wp_verify_nonce( $requestData['_ajax_nonce'], 'ajax_post' ) ) {
					$error = __('Invalid nonce in request','kc-lang');
					wp_send_json(kcThrowExceptionResponse( $error, 419 ));
				}

                //call function
				$this->call( $route );
			}else{
                $error = __('Route not found','kc-lang');
				wp_send_json(kcThrowExceptionResponse($error, 404 ));
            }

		} catch ( Exception $e ) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			header( "Status: $code $message" );

			wp_send_json( [
				'status'  => false,
				'message' => $e->getMessage()
			] );

		}
	}

	public function ajaxGet() {

		$request = new KCRequest();

		$requestData = $request->getInputs();

		if ( $requestData === '' ) {
			$requestData = json_decode( file_get_contents( "php://input" ), true );
		}

        try {
            //check if request method is get method
            if ( strtolower( sanitize_textarea_field(wp_unslash($_SERVER['REQUEST_METHOD'])) ) !== 'get' ) {
                $error = 'Method is not allowed';
	            wp_send_json(kcThrowExceptionResponse( $error, 405 ));
            }

			$this->routes = (new KCRoutes())->routes();

            //check if request route exists in route array
            if ( isset( $this->routes[ $requestData['route_name'] ] ) ) {

                //get request route value from route array
                $route = $this->routes[ $requestData['route_name'] ];

                //check if request route method is same as required method
				if ( strtolower( $route['method'] ) !== 'get' ) {
					$error = __('Method is not allowed','kc-lang');
					wp_send_json(kcThrowExceptionResponse( $error, 405 ));
				}

	            if ( ! wp_verify_nonce( $requestData['_ajax_nonce'], 'ajax_get' ) ) {
		            $error = __('Invalid nonce in request','kc-lang');
		            wp_send_json(kcThrowExceptionResponse( $error, 419 ));
	            }
                //call function
				$this->call( $route );

			}else{
                $error = __('Route not found','kc-lang');
	            wp_send_json(kcThrowExceptionResponse( $error, 404 ));
            }

		} catch ( Exception $e ) {

			$code    = $e->getCode();
			$message = $e->getMessage();

			header( "Status: $code $message" );

	        wp_send_json( [
				'status'  => false,
				'message' => $e->getMessage()
			] );
		}
		
	}

	public function call( $route ) {

		$cluster = explode( '@', $route['action'] );

		$namespace = !empty($route['namespace']) ? $route['namespace'] : $this->controllerPath;
		$controller = $namespace . $cluster[0];
		$function   = $cluster[1];
		if(class_exists($controller)){
			( new $controller )->$function();
		}else{
			wp_send_json(kcThrowExceptionResponse( __('Controller Class not found ','kc-lang'), 405 ) );
		}
		
	}

}