{*
* NOTICE OF LICENSE
*
* The MIT License (MIT)
*
* Copyright (c) 2015-2016 CoinGate
*
* Permission is hereby granted, free of charge, to any person obtaining a copy of
* this software and associated documentation files (the "Software"), to deal in
* the Software without restriction, including without limitation the rights to use,
* copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
* and to permit persons to whom the Software is furnished to do so, subject
* to the following conditions:
*
* The above copyright notice and this permission notice shall be included in all
* copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
* WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR
* IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*
*  @author    CoinGate <info@coingate.com>
*  @copyright 2015-2016 CoinGate
*  @license   https://github.com/coingate/prestashop-plugin/blob/master/LICENSE  The MIT License (MIT)
*}
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
