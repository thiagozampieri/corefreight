<?php
/**
 * Created by PhpStorm.
 * User: thiagozampieri
 * Date: 17/09/18
 * Time: 21:14
 */

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(1);
date_default_timezone_set('America/Sao_Paulo');

class DNE
{

    private $version = 'V0612';
    private $zipcode;
    private $host="localhost", $user="root", $bd="dne", $pass="root";
    private $con = null;

    public function __construct()
    {
        $this->connect();
    }

    private function connect()
    {
        $connection = fsockopen($this->host, 3306,$errno, $errstr, 30);
        if ($connection)
        {
            $con = @new mysqli($this->host, $this->user, $this->pass, $this->bd);
            if (!mysqli_connect_errno()) $this->con = $con;
        }
        return $this;
    }

    private function validate($username, $password)
    {
        $_array = array('yepcomm' => '123456');
        if ( $_array[$username] == $password )	return true;

        return false;
    }

    public function getAddress($zipcode, $username, $password)
    {
        $_array = array();

        $xml  = '<?xml version="1.0" encoding="ISO-8859-1"?>';
        $xml .= '<dne>';
        $xml .= '	<author>CORELAB</author>';
        //$xml .= '	<server>'.$this->host.'</server>';
        $xml .= '	<datetime>'.date("Y-m-d H:i").'</datetime>';
        $xml .= '	<version>'.$this->version.'</version>';
        $xml .= '	<username>'.strtoupper($username).'</username>';
        $xml .= '	<source>DNE</source>';
        $xml .= '	<remoteAddr>'.$_SERVER['REMOTE_ADDR'].'</remoteAddr>';
        //$xml .= '	<zipcode>'.str_replace(array("-", "."), "", $zipcode).'</zipcode>';
        $xml .= '	<resultGetAddress>';
        $_xml = "";

        if ($this->con != null)
        {
            if ($this->validate($username, $password))
            {
                if ( $this->checkZipcode($zipcode) )
                {
                    /* PAIS, ABREV. PAIS, ESTADO, UF */
                    $sql = 'SELECT dgp.sigla_pais_1, dgp.nome_pais_portugues,
								   dguf.sigla_uf, dguf.nome_oficial_uf
							  FROM DNE_GU_PAISES dgp, DNE_GU_UNIDADES_FEDERACAO dguf, DNE_GU_FAIXAS_CEP_UF dgfcu
							 WHERE dgp.sigla_pais_1    = dguf.sigla_pais_1
							   AND dguf.chave_uf_dne   = dgfcu.chave_uf_dne
							   AND dguf.sigla_uf       = dgfcu.sigla_uf
							   AND ( "'.$this->zipcode.'" 
									BETWEEN dgfcu.cep_ini_fx_uf 
										AND dgfcu.cep_fim_fx_uf
									)
							';
                    $result = $this->sqlToArray($sql);
                    if (is_array($result))
                    {
                        $_array['country']			= $result['nome_pais_portugues'];
                        $_array['countryAbbrev']	= $result['sigla_pais_1'];
                        $_array['state']			= $result['nome_oficial_uf'];
                        $_array['uf']				= $result['sigla_uf'];

                    }

                    /* CIDADE, ABREV. CIDADE */
                    $sql = 'SELECT dgloc.chave_loc_dne, dgloc.nome_ofi_localidade, dgloc.abrev_loc_rec_ect, dgloc.codigo_mun_ibge
							  FROM DNE_GU_LOCALIDADES dgloc 
							 WHERE dgloc.cep_localidade = "'.$this->zipcode.'"
							   AND dgloc.sigla_uf       = "'.$_array['uf'].'"
						   ';
                    $result = $this->sqlToArray($sql);
                    $_zipcode_unique = true;

                    if (!is_array($result))
                    {
                        $sql = 'SELECT dgloc.chave_loc_dne, dgloc.nome_ofi_localidade, dgloc.abrev_loc_rec_ect, dgloc.codigo_mun_ibge
							  FROM DNE_GU_LOCALIDADES dgloc, DNE_GU_FAIXAS_CEP_LOCALIDADE dgfcloc
							 WHERE dgloc.chave_loc_dne = dgfcloc.chave_loc_dne
							   AND ( "'.$this->zipcode.'" 
									BETWEEN dgfcloc.cep_ini_fx_loc_codif  
										AND dgfcloc.cep_fim_fx_loc_codif
									)
							   AND dgloc.sigla_uf = "'.$_array['uf'].'"
						   ';
                        $result = $this->sqlToArray($sql);

                        $_zipcode_unique = false;
                    }

                    if (is_array($result))
                    {
                        $_add['chave_loc_dne']  = $result['chave_loc_dne'];
                        $_array['city']			= $result['nome_ofi_localidade'];
                        $_array['cityAbbrev']	= $result['abrev_loc_rec_ect'];
                        //Thiago - 03.04.2009 - Incluir Codigo Municipio do IBGE
                        $_array['codeCityIBGE']	= $result['codigo_mun_ibge'];

                    }

                    /* BAIRRO, ABREV. BAIRRO */
                    $sql = 'SELECT dgb.chave_bai_dne, dgb.nome_ofi_bai, dgb.abre_bai_rec_ect
							  FROM DNE_GU_BAIRROS dgb, DNE_GU_FAIXAS_CEP_BAIRRO dgfcb
							 WHERE dgb.chave_loc_dne = "'.$_add['chave_loc_dne'].'"
							   AND dgb.sigla_uf_bai  = "'.$_array['uf'].'"
							   AND ( "'.$this->zipcode.'" 
									BETWEEN dgfcb.cep_ini_fx_bai
										AND dgfcb.cep_fim_fx_bai
									)
							   AND dgb.chave_bai_dne = dgfcb.chave_bai_dne
							';
                    $result = $this->sqlToArray($sql);

                    if (!is_array($result))
                    {
                        $sql = 'SELECT dgb.chave_bai_dne, dgb.nome_ofi_bai, dgb.abre_bai_rec_ect
							  FROM DNE_GU_BAIRROS dgb
							 WHERE dgb.chave_loc_dne = "'.$_add['chave_loc_dne'].'"
							   AND dgb.sigla_uf_bai  = "'.$_array['uf'].'"
								';
                        $result = $this->sqlToArray($sql);
                    }

                    if (is_array($result))
                    {
                        $result['nome_ofi_bai'] = str_replace(" ø ", " - ", $result['nome_ofi_bai']);
                        $result['abre_bai_rec_ect'] = str_replace(" ø ", " - ", $result['abre_bai_rec_ect']);
                        if ( $result['abre_bai_rec_ect'] == '' AND $result['nome_ofi_bai'] != '' ) $result['abre_bai_rec_ect'] = $result['nome_ofi_bai'];

                        $_add['chave_bai_dne']  = $result['chave_bai_dne'];
                        $_array['region']		= $result['nome_ofi_bai'];
                        $_array['regionAbbrev']	= $result['abre_bai_rec_ect'];

                    }

                    ###############################################
                    ### VERIFY IF ZIPCODE IS UNIQUE IN THE CITY ###
                    ###############################################
                    if ( !$_zipcode_unique )
                    {
                        /* TIPO LOGRADOURO, ABREV. TIPO LOGRADOURO, LOGRADOURO, ABREV. LOGRADOURO, INFO_ADICIONAL,  */
                        $sql = 'SELECT dgtl.abrev_tp_log_rec_ect,
									   dglog.chave_logradouro_dne, dglog.tipo_ofi_logradouro, dglog.preposicao, dglog.tit_pat_ofi_logradouro, 
									   dglog.nome_ofi_logradouro, dglog.abrev_log_rec_ect, dglog.info_adicional
								  FROM DNE_GU_LOGRADOUROS dglog, DNE_GU_TIPOS_LOGRADOURO dgtl
								 WHERE dglog.chave_loc_dne 		 = "'.$_add['chave_loc_dne'].'"
								   AND dglog.sigla_uf      		 = "'.$_array['uf'].'"
								   AND 
									(
										(    "'.$_add['chave_bai_dne'].'" >= dglog.chave_bai_ini_dne
										 AND "'.$_add['chave_bai_dne'].'" <= dglog.chave_bai_fim_dne
										)
										OR
										(    "'.$_add['chave_bai_dne'].'" >= dglog.chave_bai_fim_dne
										 AND "'.$_add['chave_bai_dne'].'" <= dglog.chave_bai_ini_dne
										)
									)
								   AND dglog.cep_logradouro      = "'.$this->zipcode.'"
								   AND dglog.tipo_ofi_logradouro = dgtl.nome_ofi_tp_log
								   AND ( dglog.ind_exis_gu_log 	 = "S" 
									OR dglog.ind_exis_gu_log 	 = "N" )
								';
                        $result = $this->sqlToArray($sql);
                        //echo $sql;

                        if (!is_array($result))
                        {
                            $sql = '							
								SELECT dgtl.abrev_tp_log_rec_ect,
									   dglog.chave_logradouro_dne, dglog.tipo_ofi_logradouro, dglog.preposicao, dglog.tit_pat_ofi_logradouro, 
									   dglog.nome_ofi_logradouro, dglog.abrev_log_rec_ect, dglog.info_adicional
								  FROM DNE_GU_LOGRADOUROS dglog, DNE_GU_TIPOS_LOGRADOURO dgtl, DNE_GU_GRANDES_USUARIOS dggu 
								 WHERE dggu.sigla_uf 			  = "'.$_array['uf'].'"
								   AND dggu.chave_loc_dne		  = "'.$_add['chave_loc_dne'].'"
								   AND dglog.chave_loc_dne 		  = "'.$_add['chave_loc_dne'].'"
								   AND dglog.sigla_uf      		  = "'.$_array['uf'].'"
								   AND 
									(
										(    "'.$_add['chave_bai_dne'].'" >= dglog.chave_bai_ini_dne
										 AND "'.$_add['chave_bai_dne'].'" <= dglog.chave_bai_fim_dne
										)
										OR
										(    "'.$_add['chave_bai_dne'].'" >= dglog.chave_bai_fim_dne
										 AND "'.$_add['chave_bai_dne'].'" <= dglog.chave_bai_ini_dne
										)
									)
								   AND dggu.chave_bai_dne   	  = "'.$_add['chave_bai_dne'].'"
								   AND dggu.cep_gu 				  = "'.$this->zipcode.'"
								   AND dglog.chave_logradouro_dne = dggu.chave_log_dne
								   AND dglog.tipo_ofi_logradouro  = dgtl.nome_ofi_tp_log
								   AND ( dglog.ind_exis_gu_log 	  = "S" 
									  OR dglog.ind_exis_gu_log 	  = "N" )
									';
                            $result = $this->sqlToArray($sql);
                            //echo $sql;
                        }

                    }else
                    {
                        $result['tipo_ofi_logradouro']  = "";
                        $result['abrev_tp_log_rec_ect'] = "";
                        $result['abrev_log_rec_ect']    = "";
                        $result['info_adicional']       = "";
                        $result['nome_ofi_logradouro']  = "";
                    }
                    $this->con->close();

                    if (is_array($result))
                    {
                        $_array['addressType']		 = $result['tipo_ofi_logradouro'];
                        $_array['addressTypeAbbrev'] = $result['abrev_tp_log_rec_ect'];
                        $_array['address']			 = str_replace("  ", " ", $this->getAbbrevTypeAddress($result['tipo_ofi_logradouro'])." ".$result['preposicao']." ".$this->getAbbrevPatent($result['tit_pat_ofi_logradouro'])." ".$result['nome_ofi_logradouro']);
                        $_array['addressAbbrev']	 = $result['abrev_log_rec_ect'];
                        $_array['addtional']		 = $result['info_adicional'];
                        $_array['suplement']		 = '';
                        $_array['number']			 = '';
                    }

                    if (is_array($_array))
                    {
                        $keys  = array_keys($_array);
                        $_xml  = '	<zipcode>'.trim($this->zipcode).'</zipcode>';

                        for ($i=0, $f=count($keys); $i<$f; $i++)
                        {
                            $_xml .= '<'.$keys[$i].'>'.$this->xmlentities($_array[$keys[$i]]).'</'.$keys[$i].'>';
                        }
                    }
                    else $_xml .= '<error number="2">STREET ADDRESSES NOT LOCATION</error>';
                }
                else $_xml .= '<error number="1">ERROR FORMAT ZIPCODE</error>';

                /*
                $_xml .= '		<addressType>RUA</addressType>';
                $_xml .= '		<addressTypeAbbrev>R</addressTypeAbbrev>';
                $_xml .= '		<address>RUA SAO VICENTE</address>';
                $_xml .= '		<addressAbbrev>R SAO VICENTE</addressAbbrev>';
                $_xml .= '		<number></number>';
                $_xml .= '		<suplement></suplement>';
                $_xml .= '		<addtional></addtional>';
                $_xml .= '		<region>JARDIM PALMARES</region>';
                $_xml .= '		<regionAbbrev>JD PALMARES</regionAbbrev>';
                $_xml .= '		<city>LONDRINA</city>';
                $_xml .= '		<cityAbbrev>LONDRINA</cityAbbrev>';
                $_xml .= '		<state>PARANA</state>';
                $_xml .= '		<uf>PR</uf>';
                $_xml .= '		<country>BRASIL</country>';
                $_xml .= '		<countryAbbrev>BR</countryAbbrev>';
                */

            }
            else $_xml .= '<error number="0">USERNAME OR/AND PASSWORD NOT ALLOWED</error>';
        }
        else $_xml .= '<error number="99">WITHOUT CONNECTION</error>';

        $xml .= $_xml;
        $xml .= '	</resultGetAddress>';
        $xml .= '</dne>';

        return $xml;
    }

    private function xmlentities($text, $entities=true)
    {
        if ($entities) $text = htmlentities($text, 0, 'ISO-8859-1');

        $text = str_replace("&amp;" , "&#38;" , $text);
        $text = str_replace("&lt;"  , "&#60;" , $text);
        $text = str_replace("&gt;"  , "&#62;" , $text);
        $text = str_replace("&apos;", "&#39;" , $text);
        $text = str_replace("&quot;", "&#34;" , $text);

        return $text;
    }

    private function getAbbrevTypeAddress($typeAddress)
    {
        if ($typeAddress != '')
        {
            $sql = 'SELECT dgtp.abrev_tp_log_rec_ect 
					  FROM DNE_GU_TIPOS_LOGRADOURO dgtp
					 WHERE dgtp.nome_ofi_tp_log  = "'.$typeAddress.'"
				   ';

            $result = $this->sqlToArray($sql);
            return $result['abrev_tp_log_rec_ect'].'.';
        }
        return $typeAddress;

    }

    private function getAbbrevPatent($patent)
    {
        if ($patent != '')
        {
            $sql = 'SELECT dgtp.abrev_tit_rec_ect 
					  FROM DNE_GU_TITULOS_PATENTES dgtp
					 WHERE dgtp.nome_ofi_tit_pat   = "'.$patent.'"
				   ';

            $result = $this->sqlToArray($sql);
            return $result['abrev_tit_rec_ect'].'.';
        }
        return $patent;
    }

    private function sqlToArray($sql)
    {
        $report = null;
        if ($this->con)
        {
            $result = $this->con->query($sql);
            if ($result->num_rows > 0) $report = $result->fetch_assoc();
        }
        return $report;
    }

    private function checkZipcode($zipcode)
    {
        if ( preg_match("/^([0-9]{2})([\.]?)([0-9]{3})([-]?)([0-9]{3})$/", trim($zipcode), $data) )
        {
            $this->zipcode = $data[1].$data[3].$data[5];
            return true;
        }
        return false;
    }
}

?>
