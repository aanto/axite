	<p>
		<label>Page name</label>
		<input ax:key="$page:name" size="55" />
		<br/>
		<span class="nobr"><input ax:key="$page:public" tpl:name="$page:public" type="checkbox" /> <label>public</label></span>
	</p>

	<p>
		<label>Template</label>
		<select ax:list="/system/available_templates" ax:key="$page:template">
			<option ax:key="$item" tpl:value="$item" ax:render="key"></option>
		</select>
	</p>

	<p>
		<label>Display in</label>
		<span class="nobr"><input ax:key="$page:display_menu1" type="checkbox" /> <label>main menu</label></span>
		&nbsp;
		<span class="nobr"><input ax:key="$page:display_menu3" type="checkbox" /> <label>bottom menu</label></span>
		&nbsp;
		<span class="nobr"><span>priority</span>: <input size="3" ax:key="$page:priority" /></span>
	</p>

	<p>
		<label><span>Title image</span> (URL)</label>
		<input ax:key="$page:image_title" size="55" />
	</p>

	<p>
		<label><span>meta:Description</span></label>
		<textarea ax:key="$page:description" rows="4"></textarea>
	</p>

	<p style="text-align: right" ax:if="'$page'!='/index'">
		<button ax:if=1 onclick="axite.ui.deleteEntity('$page')">Delete</button>
		<button ax:if=1 onclick="axite.ui.moveEntity('$page')">Rename / Move</button>
		<button ax:if=1 onclick="axite.ui.copyEntity('$page')">Save new copy</button>
	</p>