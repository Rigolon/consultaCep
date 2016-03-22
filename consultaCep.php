<?php

$showErros=1;

if( $showErros )
{
	ini_set("display_errors", 0);
	ini_set('error_reporting', E_ALL);
	error_reporting(E_ALL);
}

function simple_curl( $url, $post = array(), $get = array() )
{
	# https://github.com/TobiaszCudnik/phpquery
	require_once 'phpQuery/phpQuery.php';

	$url = explode( '?', $url, 2);

	if( count($url) === 2 )
	{
		$temp_get = array();
		parse_str( $url[1], $temp_get );
		$get = array_merge( $get, $temp_get );
	}

	$ch = curl_init($url[0]."?".http_build_query($get));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_ENCODING , "gzip");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0); 
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	
	$return =  curl_exec ($ch);
	$charset = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	
	// Retorna o Resultado
	return $return;
}

function pegarCep($cep){	
	if($cep)
	{
		# https://github.com/TobiaszCudnik/phpquery
		require_once 'phpQuery/phpQuery.php';

		$data_pesquisa = array(
			'cepEntrada' => str_replace( array('.',',','-'), '', $cep ),
			'tipoCep'    => '',
			'cepTemp'    => '',
			'metodo'     => 'buscarCep'
		);
		
		$html = simple_curl( 'http://m.correios.com.br/movel/buscaCepConfirma.do', $data_pesquisa );
		
		if( $html )
		{

			phpQuery::newDocumentHTML($html, $charset='utf-8');
			
			$test_erro = pq('div.erro')->html();
			$test_erro = preg_replace('/(\s)+/s',' ',$test_erro);
			$test_erro = trim( $test_erro );
			$test_erro = trim( $test_erro , '<br>');
			$test_erro = trim( $test_erro , '-');
			$test_erro = trim( $test_erro );
			
			if( empty($test_erro) )
			{
				$dados = 
					array(
						'logradouro'=> trim(pq('.caixacampobranco .resposta:contains("Logradouro: ") + .respostadestaque:eq(0)')->html()),
						'bairro'	=> trim(pq('.caixacampobranco .resposta:contains("Bairro: ") + .respostadestaque:eq(0)')->html()),
						'cidade/uf'	=> trim(pq('.caixacampobranco .resposta:contains("Localidade / UF: ") + .respostadestaque:eq(0)')->html()),
						'cep'		=> trim(pq('.caixacampobranco .resposta:contains("CEP: ") + .respostadestaque:eq(0)')->html())
					);
				
				if( preg_match('/-/', $dados['logradouro']) )
				{
					$dados['logradouro'] = explode('-',$dados['logradouro']);
					$dados['logradouro'] = $dados['logradouro'][0];
				}
				
				$dados['cidade/uf'] = explode('/',$dados['cidade/uf']);
				$dados['cidade'] = trim($dados['cidade/uf'][0]);
				$dados['uf'] = trim($dados['cidade/uf'][1]);
				
				$dados['erro']=false;
				$dados['erroCod']=0;
				$dados['msg']='Consulta com sucesso';
				
				foreach($dados as $k => $d)
				{
					if($k=='erroCod') continue;				
					if( empty($d)) unset( $dados[$k] );
				}
				
				unset($dados['cidade/uf']);

			}
			else
			{
				$dados = array(
					'erro'   => TRUE,
					'erroCod'=> 1,
					'msg'    => $test_erro
				);			
			}
		}
		else
		{
			$dados = array(
				'erro'   => TRUE,
				'erroCod'=> 2,
				'msg'    => 'Não foi possível conectar aos correios, tente mais tarde'
			);
		}
	}
	else
	{
		$dados = array(
			'erro'   => TRUE,
			'erroCod'=> 3,
			'msg'    => 'Obrigatório enviar o CEP para consulta'
		);
	}
	
	return $dados;

}

$cep = ( isset( $_REQUEST['cep'] ) && !empty( $_REQUEST['cep'] ) ) 
	? $_REQUEST['cep']
	: false;

$n = 0;

do {
	$dados = pegarCep( $cep );
	$n++;
} while ( ($dados['erroCod'] !=0 && $dados['erroCod'] !=3 ) && $n < 5 );

header('Content-Type: application/json; charset=utf-8', TRUE);
die( json_encode($dados) );
