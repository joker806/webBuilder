Ext.define( 'WebBuilder.component.TemplateSelectorPopup',
{
	extend : 'Ext.window.Window',
	
	title : 'Výběr šablony',
	
	okBtnTitle     : 'Použít',
	cancelBtnTitle : 'Storno',
	
	/**
	 * @required
	 * @cfg {extAdmin.Environment} env
	 */
	env : null,

	/**
	 * @cfg {extAdmin.Module/String} module
	 */
	module : '\\WebBuilder\\WebBuilder\\ExtAdmin\\TemplatesManager\\TemplatesList',

	/**
	 * @cfg {Array/String} blocksLoadAction
	 */
	loadAction : 'loadListData',

	/**
	 * Editor initialization
	 *
	 */
	initComponent : function()
	{
		var me = this;

		me.module = me.env.getModule( me.module );
		
		me.list = Ext.create( 'Ext.grid.Panel', {
			forceFit : true,
			
			store : me.module.createStore({
				loadAction : me.loadAction,
				autoLoad   : true,
				
				model : extAdmin.component.Model.create({
					name : { type : 'string' }
				}),
				implicitModel : true
			}),
			
			columns : [{
				dataIndex : 'name',
				header    : 'Název'				
			}]
		});

		Ext.apply( me, {
			layout : 'fit',
			width  : 400,
			height : 200,
			
			items   : [ me.list ],
			buttons : [{
				text    : me.okBtnTitle,
				iconCls : 'i-ok',
				handler : me.onSelect,
				scope   : me
			},{
				text    : me.cancelBtnTitle,
				iconCls : 'i-cancel',
				handler : me.close,
				scope   : me
			}]
		});

		me.callParent();
	},
	
	onSelect : function()
	{
		var me      = this,
		    records = me.list.getSelectionModel().getSelection();
		
		me.close();
		Ext.callback( me.handler, me.scope, [ records ] );
	}
});