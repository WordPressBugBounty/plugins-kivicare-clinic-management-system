var el = wp.element.createElement,
components        = wp.components,
blockControls     = wp.blockEditor.BlockControls,
inspectorControls = wp.blockEditor.InspectorControls;
SelectControl		= components.SelectControl;
ToggleControl		= components.ToggleControl;
TextareaControl = components.TextareaControl;
PanelBody = components.PanelBody;
PanelRow = components.PanelRow;
Fragment = wp.element.Fragment;
Button = components.Button;

UserRoleDefault = 'kiviCare_doctor , kiviCare_patient , kiviCare_receptionist';

wp.blocks.registerBlockType( 'kivi-care/register-login', {
    title: 'Kivi Register-Login Dashboard Widget(New)',
    description:'A custom block for Register-Login User widget',
    category: 'kivi-appointment-widget',
    icon: el('svg', { width: '24', height: '24', viewBox: "0 0 361 357" },
        el('path', {style: { stroke: '#fff', strokeLinejoin: 'round', fillRule: 'evenodd', strokeWidth: '4px'}, class: "cls-1", d: "M342.294,253.118s14.088,0.306,14.088-17.572V112.827s-1.169-17.858-14.088-17.858H257.767S99.28,103.768,99.28,253.118V334.23S96.554,352.1,116.805,352.1H240.158s17.609,0.11,17.609-17.865V253.118h84.527Z"}),
        el('path', {style: {fill: "#f68685", fillRule: 'evenodd'}, class: "cls-2", d: "M99.457,14.39S99.15,0.3,117.074.3H240.108s17.9,1.169,17.9,14.094V98.953S249.19,257.508,99.457,257.508H18.137S0.226,260.235.226,239.976V116.57S0.115,98.953,18.137,98.953h81.32V14.39Z"}),
        el('path', {style: {fill: "#4874dc", fillRule: 'evenodd'}, class: "cls-3", d: "M342.294,253.118s14.088,0.306,14.088-17.572V112.827s-1.169-17.858-14.088-17.858H257.767S99.28,103.768,99.28,253.118V334.23S96.554,352.1,116.805,352.1H240.158s17.609,0.11,17.609-17.865V253.118h84.527Z"}),
    ),
    attributes: {
        short_code: {
            type: 'string',
            default: '[kivicareRegisterLogin]'
        },
        loginTab: {
            type: 'boolean',
            default: false
        },
        registerTab: {
            type: 'boolean',
            default: false
        },
        userRole: {
            type: 'string',
            default: ''
        },
    },
    edit: function( props ) {
        function getShortCode(props, attributes) {
            var attrs = [];

            if( props.attributes.loginTab ){
                attrs.push( 'login=' + props.attributes.loginTab )
            }

            if( props.attributes.registerTab ){
                attrs.push( 'register=' + props.attributes.registerTab )
            }

            if( props.attributes.userRole !== "" ){
                attrs.push( 'userRoles="' + props.attributes.userRole + '"') ;
            }

            var short_code = '[kivicareRegisterLogin' + (attrs.length ? ' ' : '') + attrs.join(' ') + ']';

            props.setAttributes({short_code: short_code});

            return short_code;
        }

        function onChangeLoginToggle(value){
            props.setAttributes({loginTab: value});
        }
        function onChangeRegisterToggle(value){
            props.setAttributes({registerTab: value});
        }
        function onChangeUserRoleList(value){
            props.setAttributes({userRole: value});
        }

        let temp =  [
            el('div', {},
                getShortCode(props)
            )
        ];
        temp.push(
            el(
                Fragment,
                null,
                el(
                    inspectorControls,
                    null,
                    el(
                        PanelBody,
                        title='Kivicare Setting',
                        el(
                            ToggleControl,
                            {
                                label: 'Show Login Tab',
                                checked: props.attributes.loginTab,
                                onChange: onChangeLoginToggle
                            }
                        ),
                        el(
                            ToggleControl,
                            {
                                label: 'Show Register Tab',
                                checked: props.attributes.registerTab,
                                onChange: onChangeRegisterToggle
                            }
                        ),
                        el(
                            TextareaControl,
                            {
                                label: 'User Role List',
                                value:UserRoleDefault,
                                readOnly:true
                            }
                        ),
                        el(
                            TextareaControl,
                            {
                                label: 'Enter User Role',
                                help:'Enter User Roles seprated by comma (,)',
                                value:props.attributes.userRole,
                                onChange: onChangeUserRoleList
                            }
                        )
                    )
                )

            )
        )
        return temp;
    },
    save: function( props ){
        return (
            el('div', {},
                props.attributes.short_code
            )
        )
    }
});