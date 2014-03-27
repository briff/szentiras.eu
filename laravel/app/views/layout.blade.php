<!DOCTYPE html>

<html lang="hu">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="HandheldFriendly" content="True">
	<meta name="MobileOptimized" content="320">
        
        <title>{{ $pageTitle or "" }}</title>

	<meta property="fb:admins" content="1819502454" />
	<meta name="description" content="A teljes Szentírás, azaz a Biblia magyarul az interneten: katolikus és protestáns fordításban">
	<meta name="keywords" content="biblia, katolikus, protestáns, szentírás, keresztény, keresztyén, református, hivatalos">
	<meta name="author" content="szentiras.hu">
	<meta http-equiv="cleartype" content="on">

	<meta property="og:image" content="http://szentiras.hu/img/biblia.jpg">
        {{ $meta or "" }}
	

        <link rel="shortcut icon" type="image/vnd.microsoft.icon" href="/img/favicon.ico">
        <link rel="stylesheet" href="/css/html5reset.css">
        <link rel="stylesheet" href="/css/responsivegridsystem.css">
        <link rel="stylesheet" href="/css/col.css">
        <link rel="stylesheet" href="/css/2cols.css">
        <link rel="stylesheet" href="/css/search.css">
        <link rel="stylesheet" href="/css/style.css">
        
        <script src="/js/modernizr-2.5.3-min.js"></script>
        <script src="/js/search.js"></script>
    </head>
    <body>
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
  ga('create', 'UA-36302080-1', 'szentiras.hu');
  ga('send', 'pageview');
</script>        

        <div id="skiptomain"><a href="#maincontent">Irány a tartalom</a></div>
        <div id="wrapper">
                <div id="headcontainer">
                    <header>
                        <h1><a href="/" style="color:white">Szentírás.hu <sup>v4</sup></a></h1>
                            <h2>Katolikus bibliafordítások az interneten</h2>
                    </header>
                </div>
                <div id="maincontentcontainer">
                    <div id="maincontent">
                        <div class="section group">	
                            <div class="col span_2_of_2" style="float: right;position: relative;">
                                <div id="share">{{ $share or "" }}</div>
                                {{ isset($title) ? "<h4>${title}</h4>":"" }}
                                @yield('hirek')
                                @yield('content')
                                <br />
                                @yield('abbrevlist')
                            </div>
                            <div class="col span_1_of_2" style="position: relative;">
                                @yield('menu')
                            </div>
                        </div>			
                        <div class="section group" style="margin-top:30px">	
                            <div class="col span_2_of_2" style="float: right;position: relative;">
                                @yield('comments')
                        </div>
                    </div>
                </div>
            </div>
            <div id="footercontainer">
                <footer class="group">
                    <div class="col span_1_of_2">
                        {{ $copyright or "" }}
                    </div>
                    <div class="col span_2_of_2">
                        <p>Kérdések, ötletek, problémák: <a href='mailto:eleklaszlosj@gmail.com'>Elek László SJ</a> (<a href="http://jezsuita.hu">JTMR</a>)</p>
                    </div>
                    <br class="breaker" />
                </footer>
            </div>
        </div>
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
	<script>window.jQuery || document.write('<script src="js/jquery-1.7.2.min.js"><\/script>')</script>
        <script src="/js/news.js"></script>
    </body>
</html>
