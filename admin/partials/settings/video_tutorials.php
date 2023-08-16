<?php
$videos = [
    [
        'link'  => 'fgM73ZOmBrg',
        'title' => 'Plugin overview',
    ],
    [
        'link'  => 'R_cNuZEdVX0',
        'title' => 'Carrier configuration',
    ],
    [
        'link'  => 'sPBZk1-9IpE',
        'title' => 'Order postage',
    ],
    [
        'link'  => 'LhcFeNDckzM',
        'title' => 'PickUp point order',
    ],
    [
        'link'  => 'u0XqhTMJekg',
        'title' => 'CN23 order',
    ],
    [
        'link'  => 'CagivW3GyqU',
        'title' => 'Multi-parcels',
    ],
    [
        'link'  => 'HskD5PoG9zc',
        'title' => 'Multi-parcels OM',
    ],
    [
        'link'  => 'UaAShJFCkB8',
        'title' => 'Parcel tracking',
    ],
    [
        'link'  => '5Xfaexhdqtg',
        'title' => 'DDP',
    ],
    [
        'link'  => '48eEexMC_Vo',
        'title' => 'Customs 2021',
    ],
    [
        'link'  => 'lfHFKScib3E',
        'title' => 'Deposit of bordereau',
    ],
    [
        'link'  => '8ftc0L2s4qg',
        'title' => 'Thermal printing kit',
    ],
]
?>
<tr>
	<td colspan="2">
		<style>
			#lpc_videos{
				width: 100%;
				text-align: center;
			}

			.lpc_video_tutorial{
				display: inline-block;
				margin: 10px;
				padding: 1rem;
				background-color: #dfdfdf;
			}

			.lpc_video_tutorial iframe{
				border: none;
			}

			.lpc_video_label{
				text-align: center;
				font-weight: bold;
				padding: 1rem;
			}

			.button-primary.woocommerce-save-button{
				display: none;
			}
		</style>
		<div id="lpc_videos">
            <?php
            $i = 1;
            foreach ($videos as $video) {
                ?>
				<div class="lpc_video_tutorial">
					<iframe width="500"
							height="280"
							src="https://www.youtube.com/embed/<?php echo esc_attr($video['link']); ?>"
							title="<?php esc_attr_e($video['title'], 'wc_colissimo'); ?>"
							allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
							allowfullscreen>
					</iframe>
					<div class="lpc_video_label"><?php echo esc_html($i . '. ' . __($video['title'], 'wc_colissimo')); ?></div>
				</div>
                <?php
                $i ++;
            }
            ?>
		</div>
	</td>
</tr>
