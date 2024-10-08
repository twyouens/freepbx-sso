<div class="row">
	<div id="login-window" class="col-md-4 col-md-offset-3 col-sm-6 col-sm-offset-2 col-xs-8 col-xs-offset-1" style="<?php echo (!empty($error_warning) || !empty($error_danger)) ? 'height: 350px;' : 'height:300px;'?>">
		<form id="frm-login" method="POST" action="?display=dashboard">
			<input type="hidden" name="token" value="<?php echo $token?>">
			<h2 class="header text-center"><?php echo _('User Control Panel')?></h2>
			<?php if(!empty($error_warning)) {?>
				<div class="alert alert-warning"><?php echo $error_warning?></div>
			<?php } ?>
			<?php if(!empty($error_danger)) {?>
				<div class="alert alert-danger"><?php echo $error_danger?></div>
			<?php } ?>
			<div class="alert alert-warning jsalert" style="display:none;"></div>
			<div id="error-msg" class="alert alert-danger" style="display:none"></div>
			<div class="input-group input-margin">
				<span class="input-group-addon"><i class="fa fa-user fa-fw"></i></span>
				<input type="text" name="username" class="form-control" placeholder="<?php echo _('Username')?>" autocapitalize="off" autocorrect="off">
			</div>
			<?php 
				$lhideClass = 'lhide';
				if($hideLogin) {
					$lhideClass = '';
				}
			?>
			<?php if(!$hideLogin) { ?>
			<div class="lshow">
				<div class="input-group input-margin">
					<span class="input-group-addon"><i class="fa fa-key fa-fw"></i></span>
					<input type="password" name="password" class="form-control" placeholder="<?php echo _('Password')?>" autocapitalize="off" autocorrect="off">
				</div>
			</div>
			<?php } ?>
			<div class="<?php echo $lhideClass ?> text-center">
				<?php echo _('or')?>
			</div>
			<div class="<?php echo $lhideClass ?>">
				<div class="input-group input-margin">
					<span class="input-group-addon"><i class="fa fa-envelope fa-fw"></i></span>
					<input type="text" name="email" class="form-control" placeholder="<?php echo _('Email')?>" autocapitalize="off" autocorrect="off">
				</div>
			</div>
			<div class="row">
				<div class="col-md-12 col-ºsm-12">
					<table class="extras">
						<tr class="action-switch">
							<?php if(!$hideLogin) { ?>
							<td colspan="2" class="lshow"><span data-show="lhide" data-hide="lshow"><?php echo _('Forgot Password')?> <i class="fa fa-question"></i></span></td>
							<?php } ?>
							<td colspan="2" class="lhide"><span data-show="lshow" data-hide="lhide"><i class="fa fa-sign-in"></i> <?php echo _('Login')?></span></td>
						</tr>
						<?php if(!$hideLogin) { ?>
						<tr class="lshow remember-me">
							<td class="text"><?php echo _('Remember Me')?></td>
							<td id="rm-checkbox" class="checkbox-c">
								<div class="onoffswitch">
								    <input type="checkbox" name="rememberme" class="onoffswitch-checkbox" id="rememberme">
								    <label class="onoffswitch-label" for="rememberme">
								        <div class="onoffswitch-inner"></div>
								        <div class="onoffswitch-switch"></div>
								    </label>
								</div>
							</td>
						</tr>
						<?php } ?>
						<tr>
							<td colspan="3" class="button-row">
								<?php if(!$hideLogin) { 
								?>
									<button type="submit" id="btn-login" class="btn btn-default lshow" disabled><?php echo _('Loading...')?></button>
								<?php } ?>
								<button type="button" id="btn-forgot" class="btn btn-default <?php echo $lhideClass ?>"><?php echo _('Send Me A Password Reset Link')?></button>
							</td>
						</tr>
                        <tr>
							<td colspan="3" class="button-row">
                                <div class="d-grid gap-2 mt-3">
								    <a href="/ucp/customsso.php"><button type="button" id="btn-login" class="btn btn-default lshow" style="color: #ffffff;background-color: #4576f4;border-color: #7aa5ff;font-size:16px">Sign in with SSO</button></a>
                                </div>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</form>
		<div class="extra-info pull-left"><?php echo session_id()?></div>
		<div class="extra-info pull-right"><?php echo $_SERVER['REMOTE_ADDR']?></div>
	</div>
	<?php
		$isMFALicensed = 0;
		if (\FreePBX::Modules()->checkStatus('pbxmfa') && \FreePBX::Pbxmfa()->isLicensed()) { 
			echo \FreePBX::Pbxmfa()->otpPage('ucp');
			$isMFALicensed = 1;
		}
	?>
	<script type="text/javascript">
		window.isMFALicensed='<?php echo $isMFALicensed;?>';
	</script>
</div>