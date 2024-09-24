var el = wp.element.createElement,
components        = wp.components,
blockControls     = wp.blockEditor.BlockControls,
inspectorControls = wp.blockEditor.InspectorControls;
SelectControl		= components.SelectControl;
TextareaControl = components.TextareaControl;
PanelBody = components.PanelBody;
PanelRow = components.PanelRow;
Fragment = wp.element.Fragment;
Button = components.Button;

const DataListNew = window.clincDataNew;

var mappingDataNew = DataListNew.mappingData;

const clinicList1New =  DataListNew.clinics;
const clinicListNew = [ {value: 0, label: '-----'} ];
const newClinicListNew = clinicList1New.map(myClinicList);
function myClinicList(value) {
    clinicListNew.push({value: value.id, label: value.name });
}

var doctorListNew = '';
var doctorIDListNew = '';

wp.blocks.registerBlockType( 'kivi-care/book-appointment-widget', {
    title: 'Kivi Appointment Widget (New)',
    description:'A custom block for showing book appointment widget',
    category: 'kivi-appointment-widget',
    icon: el('svg', { width: '24', height: '24', viewBox: "0 0 361 357" },
        el('path', {style: { stroke: '#fff', strokeLinejoin: 'round', fillRule: 'evenodd', strokeWidth: '4px'}, className: "cls-1", d: "M342.294,253.118s14.088,0.306,14.088-17.572V112.827s-1.169-17.858-14.088-17.858H257.767S99.28,103.768,99.28,253.118V334.23S96.554,352.1,116.805,352.1H240.158s17.609,0.11,17.609-17.865V253.118h84.527Z"}),
        el('path', {style: {fill: "#f68685", fillRule: 'evenodd'}, className: "cls-2", d: "M99.457,14.39S99.15,0.3,117.074.3H240.108s17.9,1.169,17.9,14.094V98.953S249.19,257.508,99.457,257.508H18.137S0.226,260.235.226,239.976V116.57S0.115,98.953,18.137,98.953h81.32V14.39Z"}),
        el('path', {style: {fill: "#4874dc", fillRule: 'evenodd'}, className: "cls-3", d: "M342.294,253.118s14.088,0.306,14.088-17.572V112.827s-1.169-17.858-14.088-17.858H257.767S99.28,103.768,99.28,253.118V334.23S96.554,352.1,116.805,352.1H240.158s17.609,0.11,17.609-17.865V253.118h84.527Z"}),
    ),
    attributes: {
        short_code: {
            type: 'string',
            default: '[kivicareBookAppointment]'
        },
        clinicId: {
            type: 'integer',
            default: 0
        },
        doctorId: {
            type: 'string',
            default: ''
        }
    },
    edit: function( props ) {
        function getShortCode(props) {
            var attrs = [];

            if(DataListNew.proActive){
                if( props.attributes.clinicId > 0 )
                {
                    attrs.push( 'clinic_id=' + props.attributes.clinicId )
                }

                if( props.attributes.doctorId !== '' )
                {
                    attrs.push( 'doctor_id=' + props.attributes.doctorId )
                }
            }

            var short_code = '[kivicareBookAppointment' + (attrs.length ? ' ' : '') + attrs.join(' ') + ']';

            props.setAttributes({short_code: short_code});

            return short_code;
        }

        function onChangeClinic( clinicId )
        {
            props.setAttributes( { clinicId: parseInt( clinicId ) } );

            props.setAttributes( { doctorId: '' } );

            var clinic_vise_doctors = [];

            doctorListNew = '';

            doctorIDListNew = '';

            if(DataListNew.proActive){
                const clinic_doctors = DataListNew.mappingData.filter(item => item.clinic_id == clinicId);

                if(clinic_doctors.length !== undefined && clinic_doctors.length > 0){
                    clinic_doctors.forEach(function(item) {
                        Object.keys(item).forEach(function(key) {
                            if(key === 'doctor_id') {
                                DataListNew.doctors.forEach(function(doctor, index){
                                    if(doctor.ID == item[key]) {
                                        clinic_vise_doctors.push(doctor);
                                    }
                                });
                            }
                        });
                    });
                }
            }else{
                DataListNew.doctors.forEach(function(doctor, index){
                    clinic_vise_doctors.push(doctor);
                });
            }
            const newDoctorsList = clinic_vise_doctors.map(myDoctorList);
            function myDoctorList(value,key) {
                doctorIDListNew = doctorIDListNew + (doctorIDListNew === '' ? '' : ',') + value.data.ID;
                doctorListNew = doctorListNew + value.data.display_name +'(ID:' +value.data.ID+'), ';
                if(clinic_vise_doctors.length === parseInt(key) + 1){
                    onChangeDoctor( doctorIDListNew)
                }
            }

        }

        function onChangeDoctor( doctorId )
        {
            doctorIDListNew = doctorId
            props.setAttributes( { doctorId:  doctorId  } );
        }

        function resetShortCode(){

            props.setAttributes( { clinicId: parseInt( 0 ) } );
            props.setAttributes( { doctorId: '' } );

        }

        let temp =  [
            el('div', {},
                getShortCode(props)
            )
        ];
        if(DataListNew.proActive){
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
                                SelectControl,
                                {
                                    label: 'Clinic',
                                    value: props.attributes.clinicId,
                                    options: clinicListNew,
                                    onChange: onChangeClinic
                                }
                            ),
                            (props.attributes.clinicId == 0 ? '' : el(
                                    TextareaControl,
                                    {
                                        label: 'Doctor list',
                                        value:doctorListNew,
                                        readOnly:true
                                    }
                                )
                            ),
                            (props.attributes.clinicId == 0 ? '' : el(
                                    TextareaControl,
                                    {
                                        label: 'Enter Doctor Id',
                                        help:'enter doctor id from above "Doctor list" which you want to show in this appointment page',
                                        placeholder:doctorIDListNew,
                                        value:doctorIDListNew,
                                        onChange: onChangeDoctor
                                    }
                                )
                            ),
                            el(
                                Button,
                                {
                                    text: 'Reset Shortcode',
                                    variant: 'secondary',
                                    onClick: resetShortCode
                                }
                            ),
                        )
                    )

                )
            )
        }
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