<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table id="allstar_journal" cellpadding="0" cellspacing="0" width="763px" border-spacing="0">
	<thead style="background-color:#FFF;">
		<tr><td style="line-height:0"><img src="http://sys.lawyerstars.com/images/mail/express/allstar.jpg" alt="上海星瀚律师事务所" /></td></tr>
		<tr><td><img src="http://sys.lawyerstars.com/images/mail/express/<?=$header_img?>" alt="<?=$title?>" width="763px" height="361px"></td></tr>
	</thead>
	<tbody>
<?$line_id=0?>
<?foreach($articles as $article){?>
<?	$line_id++?>
<?	if($line_id>0){?>
		<tr>
			<td style="padding:0;border:0;">
				<img src="http://sys.lawyerstars.com/images/mail/express/delimiter.png" alt="_______________________________________________________" />
			</td>
		</tr>
<?	}?>
		<tr>
			<td style="border:0;padding:40px;<?if($line_id % 2 == 0){?>text-align:right;<?}?>"<?if($line_id % 2 == 0){?> align="right"<?}?>>
				<h1 style="color:#0D0080;font:25px/1.5 Simhei;"><?=$article['title']?></h1>
				<p style="width:500px;<?if($line_id % 2 == 0){?>float:right;<?}?>"><?=$article['summary']?>
					<span><a href="http://www.lawyerstars.com/article-<?=$article['aid']?>-1.html">查看全文</a></span>
				</p>
			</td>
		</tr>
<?}?>
	</tbody>
	<tfoot>
		<tr>
			<td><img src="http://sys.lawyerstars.com/images/mail/express/footer.jpg" alt="上海星瀚律师事务所" /></td>
		</tr>
	</tfoot>
</table>