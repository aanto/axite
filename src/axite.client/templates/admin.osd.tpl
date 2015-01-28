<div id="ax_adminOSD" class="axPanel">
	<form action="" method="POST">
		<span>Hello</span>, <b>admin</b>!<br/>
		<sup><button class="a" accesskey="L" type="submit" name="logout">log out</button></sup>
	</form>

	<p id="ax_systemMessage"></p>

	<button class="big" id="ax_ButtonSave" accesskey="S" onclick="axite.ui.saveAll()">Save</button><br/>
	<button class="big" id="ax_ButtonEdit" accesskey="E" onclick="axite.emit('switchEditableMode', 'edit')">Edit</button><br/>
	<button class="big" id="ax_ButtonView" accesskey="W" onclick="axite.emit('switchEditableMode', 'view')" ax:key="axite/ui/showPanel" ax:control ax:render="none">Preview</button><br/>


	<div id="ax_panelOptions" class="axOsdPanel" hidden ax:key="axite/ui/showPanel" ax:if="'$value' === 'Options'" ax:render="none">
		<button class="close" ax:key="axite/ui/showPanel" ax:control ax:render="none">&times;</button>
		<? // include 'editor.pages.tpl' ?>
		<div ax:include="/axite.client/templates/editor.pages.tpl"></div>
	</div>
	<button ax:key="axite/ui/showPanel" ax:control onclick="axite.emit('switchEditableMode', 'edit')" ax:render="none" value="Options" accesskey="O">Options</button><br/>

	<div id="ax_panelHelp" class="axOsdPanel" hidden ax:key="axite/ui/showPanel" ax:if="'$value' === 'Help'" ax:render="none">
		<button class="close" ax:key="axite/ui/showPanel" ax:control ax:render="none">&times;</button>
		<div ax:include="/axite.client/templates/admin.help.<?=$this->config['lang']?>.tpl"></div>
	</div>
	<button ax:key="axite/ui/showPanel" ax:control ax:render="none" value="Help" class="axAdminButton" accesskey="H">Help</button>
</div>

<div id="ax_rteOSD" class="invisible" ax:reject>
	<div class="rte_buttons"></div>
	<button onclick="axite.ui.rteExec('bold')"><b>b</b></button><button onclick="axite.ui.rteExec('italic')"><i>i</i></button>
	<span class="spacer"></span>
	<button onclick="axite.ui.rteExec('insertparagraph')" class="hideWhenReduced">&para;</button><button onclick="axite.ui.rteExec('insertorderedlist')" class="hideWhenReduced">123</button><button onclick="axite.ui.rteExec('insertunorderedlist')" class="hideWhenReduced">&bull; &bull; &bull;</button>
	<span class="spacer hideWhenReduced"></span>
	<button onclick="axite.ui.rteExec('insertImage')">image</button><button onclick="axite.ui.rteExec('createLink')">link</button>
</div>


<div class="hidden parking" ax:reject>
	<div id="ax_ItemControls" class="axPanel ax_ItemControls" >
		<p>
			<span class="nobr"><input ax:key="$item:public" name="$item:public" type="checkbox" /> <label>public</label></span>
		</p>
	</div>
	<span id="ax_ItemControlsImg" class="ax_ItemControlsImg" >
		<button ax:if=1 class="withIcon" onclick="axite.emit('markDelete', '$item')"><span class="icon">&#59177;</span><span>delete</span></button>
	</span>
</div>