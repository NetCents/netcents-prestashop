<div class="tab">
  <button class="tablinks" onclick="changeTab(event, 'Information')" id="defaultOpen">Information</button>
  <button class="tablinks" onclick="changeTab(event, 'Configure Settings')">Configure Settings</button>
</div>

<!-- Tab content -->
<div id="Information" class="tabcontent">
	<div class="wrapper">
	  <h2 class="netcents-information-header">Accept Bitcoin, Litecoin, Ethereum and other digital currencies on your PrestaShop store with NetCents</h2><br/>
	  <strong> What is NetCents? </strong> <br/>
	  <p>We offer a fully automated cryptocurrency processing platform and invoice system.
	  <p>
	  	<ul>
	  		<li>Install the NetCents module on PrestaShop</li>
	  		<li>Visit <a href="https://net-cents.com/sign-up" target="_blank">netcents.com</a> and create an account</li>
	  		<li>Get your API credentials and copy-paste them to the Configuration page in NetCents module</li>
	  	</ul>
	  </p>
	  <p class="sign-up"><br/>
	  	<a href="https://net-cents.com/sign-up" class="sign-up-button">Sign up on NetCents</a>
	  </p><br/>
	  <p><i> Questions? Contact support@net-cents.com ! </i></p>
	</div>
</div>

<div id="Configure Settings" class="tabcontent">
  {html_entity_decode($form|escape:'htmlall':'UTF-8')}
</div>

<script>
	document.getElementById("defaultOpen").click();
</script>