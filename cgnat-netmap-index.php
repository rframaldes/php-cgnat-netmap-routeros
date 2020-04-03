<!DOCTYPE html>
<html>
<head>
	<title>Gerador de CGNAT para RouterOS com netmap</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
	<script language="javascript">
	function dis_able()
	{
		if(document.formulario.int.value == 'null')
			document.formulario.nome.disabled=1;
		
		else
			document.formulario.nome.disabled=0;
	}
	</script>
</head>
<body>
	<?php 
		if (empty($_POST['c'])) { 
	?>
<div style="padding: 30px;">
	<form method="POST" name="formulario">
		<h3>Gerador de CGNAT para RouterOS com netmap</h3>
		<div class="form-group">
			<label for="c"><b>IP inicial</b></label>
			<input type="text" class="form-control" id="c" name="c" placeholder="100.64.0.0">
		</div>
		<div class="form-group">
			<label for="s"><b>Bloco Público</b></label>
			<input type="text" class="form-control" id="s" name="s" placeholder="200.200.200.0/26">
			<small id="obs" class="form-text text-muted">Você precisa adicionar cada IP/32 deste bloco em seu router</small>
		</div>
		<div class="form-group">
			<label for="t"><b>1 para?</b></label>
			<select class="custom-select" name="t" id="t">
			<option value="8">8 (~8000 portas por IP)</option>
			<option value="16">16 (~4000 portas por IP)</option>
			<option value="32">32 (~2000 portas por IP)</option>
			<option value="64" selected="">64 (~1000 portas por IP)</option>
			<option value="128">128 (~500 portas por IP) Não recomendado</option>
			</select>
		</div>
		<div class="form-group">
			<label for="int"><b>Interface Uplink</b></label>
			<select class="custom-select" name="int" id="int" onChange="dis_able()">
				<option value="null">Não Informar</option>
				<option value="nome">Nome da Interface</option>
				<option value="list">Interface List</option>				
			</select>
		</div>	
		<div class="form-group">
			<label for="nome"><b>Nome da interface</b></label>
			<input type="text" class="form-control" id="nome" name="nome" placeholder="sfp1 / uplink-cgnat" disabled="" >
			<small id="obs" class="form-text text-muted">Nome da sua interface uplink ou da interface-list.</small>
		</div>
		  <div class="form-group">		    
		    <label class="form-check-label" for="protocol"><b>Protocolo: </b></label>
		    <div class="form-check-inline">
			  <label class="form-check-label">
			    <input type="radio" class="form-check-input" checked="" name="protocol" value="none">TCP/UDP
			  </label>
			</div>
			<div class="form-check-inline">
			  <label class="form-check-label">
			    <input type="radio" class="form-check-input" name="protocol" value="tcpudp">TCP
			  </label>
			</div>
		    <small id="obs" class="form-text text-muted">Algumas pessoas alegam ter algum problemas fazendo para UDP.</small>
		  </div>
		<div class="form-group">
			<button type="submit" class="btn btn-primary">Gerar</button>
		</div>
	</form>
	<div align="center">
		<a href="https://github.com/remontti/php-cgnat-netmap-routeros">Código Fonte</a> | <a href="https://blog.remontti.com.br/doar">Doar</a>
	</div>
</div>
	<?php
		} 
		else {
			echo "<pre style=\"padding: 10px;\">";

			if ($_POST['int'] == "null") {
				$interface_saida = null;
				$interface_nome = null;
				$interface = null;
			}
			elseif ($_POST['int'] == "nome") {
				$interface_saida = "out-interface";
				$interface_nome = $_POST['nome'];
				$interface = " $interface_saida=\"$interface_nome\"";
			}
			else {
				echo "# Cria interface list <br />";
				echo "/interface list add name=" .$_POST['nome']. "<br /><br />";

				echo "# Não esqueça de adicionar sua interface de Uplink ao grupo ".$_POST['nome']." <br />";
				echo "#/interface list member add list=".$_POST['nome']." interface=??????? <br /><br />";

				$interface_saida = "out-interface-list";
				$interface_nome = $_POST['nome'];
				$interface = " $interface_saida=\"$interface_nome\"";
			}
			echo "# Cria regras de CGNAT <br />";
			echo "/ip firewall nat <br />";
			$subnet_rev = array(
			    '20'  => '4096',
			    '21'  => '2048',
			    '22'  => '1024',
			    '23'   => '512',
			    '24'   => '256',
			    '25'   => '128',
			    '26'    => '64',
			    '27'    => '32',
			    '28'    => '16',
			    '29'     => '8',
			    '30'     => '4',
			    '32'     => '1'
			);

			$CGNAT_IP = ip2long($_POST['c']);
			$CGNAT_START = $_POST['s'];
			$CGNAT_RULES = $_POST['t'];
			$saida_regras = array();
			$saida_jumps = array();
			$x = 1;

			$rules = explode('/', $CGNAT_START);
			$ports = ceil((65535-1024)/$CGNAT_RULES);
			$ports_start = 1025;
			$ports_end = $ports_start + $ports;

			$public = explode('.', $rules[0]);
			$CGNAT_IP_INICIAL = $CGNAT_IP;
			$checkip = $CGNAT_IP_INICIAL;

			for($i=0;$i<$CGNAT_RULES;++$i){
				
				$saida_regras[] = "add action=netmap chain=CGNAT-{$x}$interface protocol=tcp src-address=".long2ip($CGNAT_IP)."/{$rules[1]} to-addresses={$CGNAT_START} to-ports={$ports_start}-{$ports_end}";
				
				if ($_POST['protocol'] == 'none'){
					$saida_regras[] = "add action=netmap chain=CGNAT-{$x}$interface protocol=udp src-address=".long2ip($CGNAT_IP)."/{$rules[1]} to-addresses={$CGNAT_START} to-ports={$ports_start}-{$ports_end}";
				}

				$saida_regras[] = "add action=netmap chain=CGNAT-{$x}$interface src-address=".long2ip($CGNAT_IP)."/{$rules[1]} to-addresses={$CGNAT_START}";
				$CGNAT_IP += $subnet_rev[$rules[1]];

				if($i==$CGNAT_RULES-1 && $x == 1){
					$saida_jumps[] = "add chain=srcnat src-address=".long2ip($CGNAT_IP_INICIAL)."-".long2ip($CGNAT_IP-1)." action=jump jump-target=\"CGNAT-{$x}\"";
				}
				
				$check = $CGNAT_IP - $CGNAT_IP_INICIAL;
				if($check>255) {
					$saida_jumps[] = "add chain=srcnat src-address=".long2ip($CGNAT_IP_INICIAL)."-".long2ip($CGNAT_IP-1)." action=jump jump-target=\"CGNAT-{$x}\"";
					$CGNAT_IP_INICIAL = $CGNAT_IP;
					++$x;
				}
				
				$ports_start = $ports_end + 1;
				if($ports_start >= 65535) {
					$ports_start = 1025;
					$ports_end = $ports_start;
				}
				
				$ports_end += $ports;
				if($ports_end > 65535){
					$ports_end = 65535;
				}
			}

			foreach($saida_jumps as $o) {
			    echo "$o <br />";
			}
			foreach($saida_regras as $f) {
			    echo "$f <br />";
			}
			echo "<pre>";
		}
	?>
</body>
</html>