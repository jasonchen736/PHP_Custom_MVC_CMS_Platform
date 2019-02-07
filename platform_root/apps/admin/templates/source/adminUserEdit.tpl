{include file="header.tpl"}

<form action="/adminUser/{if $mode == 'edit'}updateUser{else}saveUser{/if}" method="post">
	<table id="editTable">
		<tr>
			<td id="editMenuCell">
				<a href="{$lastQuery}" class="viewContent">Return to previous search</a>
				<br /><br />
				<ul id="editMenu">
					<li class="head">&nbsp;</li>
					<li class="editMenuOption{if !$propertyMenuItem || $propertyMenuItem == 'user_main'} selected{/if}" id="user_main">User Details</li>
					<li class="editMenuOption{if $propertyMenuItem == 'user_access'} selected{/if}" id="user_access">User Access</li>
					<li class="editMenuOption{if $propertyMenuItem == 'user_groups'} selected{/if}" id="user_groups">User Groups</li>
					<li class="end">&nbsp;</li>
				</ul>
				<div id="editActionContainer">
{if $mode == 'edit'}
					<input type="hidden" name="adminUserID" value="{$adminUser.adminUserID}" />
					<input class="button" type="submit" name="submit" value="Update" />
{else}
					<input class="button" type="submit" name="submit" value="Add and Edit" />
					<input class="button" type="submit" name="submit" value="Add Another" />
{/if}
				</div>
			</td>
			<td id="editPropertyCell">
				<div id="user_mainContainer" class="propertyContainer{if $propertyMenuItem && $propertyMenuItem != 'user_main'} hidden{/if}">
					<table>
{if $mode == 'edit'}
						<tr>
							<td><span class="normalLabel">Admin User ID:</span></td>
							<td>{$adminUser.adminUserID}</td>
						</tr>
{/if}
						<tr>
							<td><span class="{if 'name'|in_array:$errorFields}errorLabel{else}normalLabel{/if}">Name:</span></td>
							<td><input type="text" name="name" value="{$adminUser.name}" /></td>
						</tr>
						<tr>
							<td><span class="{if 'email'|in_array:$errorFields}errorLabel{else}normalLabel{/if}">Email:</span></td>
							<td><input type="text" name="email" value="{$adminUser.email}" /></td>
						</tr>
						<tr>
							<td><span class="{if 'login'|in_array:$errorFields}errorLabel{else}normalLabel{/if}">Login:</span></td>
							<td><input type="text" name="login" value="{$adminUser.login}" /></td>
						</tr>
						<tr>
							<td><span class="{if 'password'|in_array:$errorFields}errorLabel{else}normalLabel{/if}">Password:</span></td>
							<td><input type="password" name="password" value="" fh:formhistory="off" /></td>
						</tr>
						<tr>
							<td><span class="{if 'status'|in_array:$errorFields}errorLabel{else}normalLabel{/if}">Status:</span></td>
							<td>
								{html_options name=status options=$statusOptions selected=$adminUser.status}
							</td>
						</tr>
					</table>
				</div>
				<div id="user_accessContainer" class="propertyContainer{if !$propertyMenuItem || $propertyMenuItem != 'user_access'} hidden{/if}">
					<table>
{foreach from=$accessSections key=access item=accessLabel}
						<tr>
							<td><input type="checkbox" name="access[{$access}]"{if $userAccess[$access]} checked="checked"{/if} /></td>
							<td align="left">&nbsp;{$accessLabel}</td>
						</tr>
{/foreach}
					</table>
				</div>
				<div id="user_groupsContainer" class="propertyContainer{if !$propertyMenuItem || $propertyMenuItem != 'user_groups'} hidden{/if}">
					<table>
{foreach from=$adminGroups item=group}
						<tr>
							<td><input type="checkbox" name="groups[{$group['adminGroupID']}]"{if isset($userGroups[$group['adminGroupID']])} checked="checked"{/if} /></td>
							<td align="left">&nbsp;{$group['name']}</td>
						</tr>
{/foreach}
					</table>
				</div>
			</td>
		</tr>
	</table>
	<input type="hidden" name="propertyMenuItem" id="propertyMenuItem" value="{$propertyMenuItem}" />
</form>

{include file="footer.tpl"}
