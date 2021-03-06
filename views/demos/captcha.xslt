<?xml version="1.0" ?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
<xsl:include href="../banshee/main.xslt" />

<!--
//
//  Content template
//
//-->
<xsl:template match="content">
<h1>Captcha demo</h1>
<p><img src="/captcha.png" /></p>
<form action="/demos/captcha" method="post">
<label for="code">Enter captcha code:</label>
<input type="text" id="code" name="code" class="form-control" />

<div class="btn-group">
<input type="submit" value="Check" class="btn btn-default" />
<a href="/demos" class="btn btn-default">Back</a>
</div>
</form>

<xsl:if test="valid">
<p>Code of previous captcha correct: <xsl:value-of select="valid" /></p>
</xsl:if>
</xsl:template>

</xsl:stylesheet>
