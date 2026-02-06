import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { __, setLocaleData } from '@wordpress/i18n';
import { KCAlert } from "../../../dashboard/components";
import '@elementor/scss/ElementorWidget.scss';


// Set locale data
setLocaleData(window.kc_frontend?.locale_data || {}, 'kivicare-clinic-management-system');

function ClinicWidget({ clinics: initialClinics, settings: initialSettings, currentUserRole }) {
  const [clinics, setClinics] = useState(initialClinics || []);
  const [settings, setSettings] = useState(initialSettings || {});
  const [specialtyFilter, setSpecialtyFilter] = useState('');
  const [serviceFilter, setServiceFilter] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [selectedClinic, setSelectedClinic] = useState(null);
  
  // Filter state for manual filtering
  const [appliedFilters, setAppliedFilters] = useState({
    service: '',
    specialty: '',
    search: ''
  });
  
  // Pagination state
  const [currentPage, setCurrentPage] = useState(1);
  const clinicsPerPage = settings.iq_kivicare_clinic_per_page || 5;

  const filteredClinics = clinics.filter(clinic => {
    const nameMatch = clinic.name.toLowerCase().includes(appliedFilters.search.toLowerCase());
    const emailMatch = clinic.email.toLowerCase().includes(appliedFilters.search.toLowerCase());
    const specialtyMatch = !appliedFilters.specialty || clinic.specialties.some(s => s.toLowerCase() === appliedFilters.specialty.toLowerCase());
    const serviceMatch = !appliedFilters.service || clinic.services.some(s => s.toLowerCase() === appliedFilters.service.toLowerCase());
    return nameMatch && emailMatch && specialtyMatch && serviceMatch;
  });

  const uniqueSpecialties = [...new Set(clinics.flatMap(c => c.specialties))].filter(Boolean);
  const uniqueServices = [...new Set(clinics.flatMap(c => c.services))].filter(Boolean);

  // Pagination calculations
  const totalPages = Math.ceil(filteredClinics.length / clinicsPerPage);
  const startIndex = (currentPage - 1) * clinicsPerPage;
  const endIndex = startIndex + clinicsPerPage;
  const paginatedClinics = filteredClinics.slice(startIndex, endIndex);

  // Pagination handlers
  const goToPreviousPage = () => {
    if (currentPage > 1) {
      setCurrentPage(currentPage - 1);
    }
  };

  const goToNextPage = () => {
    if (currentPage < totalPages) {
      setCurrentPage(currentPage + 1);
    }
  };

  // Reset to first page when filters change
  React.useEffect(() => {
    setCurrentPage(1);
  }, [appliedFilters.search, appliedFilters.service, appliedFilters.specialty]);

  // Handle filter button click
  const handleFilterClick = () => {
    setAppliedFilters({
      service: serviceFilter,
      specialty: specialtyFilter,
      search: searchTerm
    });
    setCurrentPage(1); // Reset to first page when applying filters
  };


  const handleBookClick = (clinic) => {
    // Check if current user is a doctor, clinic admin, receptionist, or administrator
    const restrictedRoles = ['administrator', 'kivicare_doctor', 'kivicare_receptionist', 'kivicare_clinic_admin'];
    
    if (currentUserRole && restrictedRoles.includes(currentUserRole)) {
      KCAlert.fire({
        title: __('Current user can not view the widget. Please open this page in incognito mode or use another browser', 'kivicare-clinic-management-system'),
        icon: 'warning',
        confirmButtonText: __('OK', 'kivicare-clinic-management-system'),
        confirmButtonColor: '#3085d6',
        confirmButtonAriaLabel: __('OK', 'kivicare-clinic-management-system'),
      });      return;
    }
    
    setSelectedClinic(clinic);
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setSelectedClinic(null);
    document.body.style.overflow = '';
  };

  useEffect(() => {
    if (showModal) {
      document.body.style.overflow = 'hidden';
      const modal = document.getElementById('kivicare-appointment-modal');
      if (modal) modal.style.display = 'block';

      const modalContainer = document.getElementById('kivicare-modal-form-container');
      if (modalContainer && typeof window.initBookAppointment === 'function') {
        // Clear previous instance
        modalContainer.innerHTML = '';

        // Create the container expected by KCBookAppointment.jsx
        const formContainer = document.createElement('div');
        formContainer.className = 'kc-book-appointment-container';

        // Preselect clinic
        if (selectedClinic?.id) {
          formContainer.setAttribute('data-selected-clinic-id', String(selectedClinic.id));
        }

        // Pass through config from the modal wrapper if present (widget order, gateways, etc.)
        ['data-widget-order', 'data-is-kivicare-pro', 'data-payment-gateways', 'data-user-login', 'data-current-user-id', 'data-page-id', 'data-query-params']
          .forEach((attr) => {
            const val = modalContainer.getAttribute(attr);
            if (val) {
              formContainer.setAttribute(attr, val);
            }
          });

        modalContainer.appendChild(formContainer);
        // Initialize the booking widget inside the modal
        window.initBookAppointment();
      }
    } else {
      const modal = document.getElementById('kivicare-appointment-modal');
      if (modal) modal.style.display = 'none';
      const modalContainer = document.getElementById('kivicare-modal-form-container');
      if (modalContainer) modalContainer.innerHTML = '';
    }

    const handleEscape = (event) => {
      if (event.key === 'Escape') closeModal();
    };
    const handleOutsideClick = (event) => {
      if (event.target.id === 'kivicare-appointment-modal') closeModal();
    };
    const handleCloseButtonClick = (event) => {
      if (event.target.classList.contains('kivicare-modal-close')) {
        closeModal();
      }
    };
    
    document.addEventListener('keydown', handleEscape);
    window.addEventListener('click', handleOutsideClick);
    document.addEventListener('click', handleCloseButtonClick);
    
    return () => {
      document.removeEventListener('keydown', handleEscape);
      window.removeEventListener('click', handleOutsideClick);
      document.removeEventListener('click', handleCloseButtonClick);
    };
  }, [showModal, selectedClinic]);

  if (clinics.length === 0) {
    return <div className="kivicare-no-clinics">{__('No clinics found.', 'kivicare-clinic-management-system')}</div>;
  }

  return (
    <div className="kivi-widget-container">
      {settings.iq_kivicare_clinic_enable_filter === 'yes' && (
        <div className="kivi-filter-container">
          <div className="kivi-filter-item">
            <select className="kivi-select" value={specialtyFilter} onChange={(e) => setSpecialtyFilter(e.target.value.toLowerCase())}>
              <option value="">{__('Filter by Speciality', 'kivicare-clinic-management-system')}</option>
              {uniqueSpecialties.map((specialty) => (
                <option key={specialty} value={specialty.toLowerCase()}>
                  {specialty}
                </option>
              ))}
            </select>
          </div>
          <div className="kivi-filter-item">
            <select className="kivi-select" value={serviceFilter} onChange={(e) => setServiceFilter(e.target.value.toLowerCase())}>
              <option value="">{__('Filter by Service', 'kivicare-clinic-management-system')}</option>
              {uniqueServices.map((service) => (
                <option key={service} value={service.toLowerCase()}>
                  {service}
                </option>
              ))}
            </select>
          </div>
          <div className="kivi-filter-item">
            <input
              type="text"
              placeholder={__('Search clinic by name, email', 'kivicare-clinic-management-system')}
              className="kivi-input"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <div className="kivi-filter-item">
            <button className="kivi-button kivi-button-primary" onClick={handleFilterClick}>{__('Filter Clinic', 'kivicare-clinic-management-system')}</button>
          </div>
        </div>
      )}

      <div className="kivi-clinic-list">
        {paginatedClinics.map((clinic, index) => (
          <div
            key={clinic.id}
            data-index={index}
            data-name={clinic.name.toLowerCase()}
            data-email={clinic.email.toLowerCase()}
            data-specialties={clinic.specialties.join(',').toLowerCase()}
            data-services={clinic.services.join(',').toLowerCase()}
          >
            {renderClinicCard(clinic, settings, handleBookClick)}
          </div>
        ))}
      </div>
      
      {/* Pagination Controls */}
      {totalPages > 1 && (
        <div className="kivicare-pagination">
          <button 
            className="kivicare-pagination-btn prev" 
            onClick={goToPreviousPage}
            disabled={currentPage === 1}
          >
            {__('Previous', 'kivicare-clinic-management-system')}
          </button>
          <span className="kivicare-pagination-info">
            {__('Page', 'kivicare-clinic-management-system')} {currentPage} {__('of', 'kivicare-clinic-management-system')} {totalPages}
          </span>
          <button 
            className="kivicare-pagination-btn next" 
            onClick={goToNextPage}
            disabled={currentPage === totalPages}
          >
            {__('Next', 'kivicare-clinic-management-system')}
          </button>
        </div>
      )}
    </div>
  );
}

function renderClinicCard(clinic, settings, handleBookClick) {
  return (
    <div className="kivi-clinic kivicare-clinic-card">
      {settings.iq_kivicare_clinic_image === 'yes' && (
        <div className="kivi-clinic-image">
          <img src={clinic.image} alt={clinic.name} className="kivicare-clinic-avtar clinic-image" />
        </div>
      )}
      <div className="kivi-clinic-content">
        {settings.iq_kivicare_clinic_name === 'yes' && (
          <div className="clinic-name">
            {settings.iq_kivicare_clinic_name_label && (
              <h6 className="kivicare-clinic-label">{settings.iq_kivicare_clinic_name_label}</h6>
            )}
            <h3 className="kivicare-clinic-name">{clinic.name}</h3>
          </div>
        )}
        {settings.iq_kivicare_clinic_speciality === 'yes' && clinic.speciality && (
          <div className="clinic-speciality">
            {settings.iq_kivicare_clinic_speciality_label && (
              <h6 className="kivicare-clinic-label">{settings.iq_kivicare_clinic_speciality_label}</h6>
            )}
            <p className="kivicare-clinic-speciality">{clinic.speciality}</p>
          </div>
        )}

        <div className="kivi-clinic-information">
          {settings.iq_kivicare_clinic_number === 'yes' && clinic.number && (
            <div className="kivi-clinic-information-item clinic-number">
              <div className="kivi-clinic-information-title">
                <h6 className="info-title kivicare-clinic-label">{settings.iq_kivicare_clinic_number_label}</h6>
              </div>
              <div className="kivi-clinic-information-content">
                <span className="kivicare-clinic-value">{clinic.number}</span>
              </div>
            </div>
          )}
          {settings.iq_kivicare_clinic_email === 'yes' && clinic.email && (
            <div className="kivi-clinic-information-item clinic-email">
              <div className="kivi-clinic-information-title">
                <h6 className="info-title kivicare-clinic-label">{settings.iq_kivicare_clinic_email_label}</h6>
              </div>
              <div className="kivi-clinic-information-content">
                <span className="kivicare-clinic-value">{clinic.email}</span>
              </div>
            </div>
          )}
          {settings.iq_kivicare_clinic_address === 'yes' && clinic.address && (
            <div className="kivi-clinic-information-item clinic-address">
              <div className="kivi-clinic-information-title">
                <h6 className="info-title kivicare-clinic-label">{settings.iq_kivicare_clinic_address_label}</h6>
              </div>
              <div className="kivi-clinic-information-content">
                <span className="kivicare-clinic-value">{clinic.address}</span>
              </div>
            </div>
          )}
        </div>

        {settings.iq_kivicare_clinic_administrator === 'yes' && (
          <div className="clinic-administrator">
            <h5 className="kivi-administrator-title kivicare-clinic-label">{settings.iq_kivicare_clinic_administrator_label}</h5>
            <div className="kivi-clinic-information">
              {settings.iq_kivicare_clinic_admin_number === 'yes' && clinic.admin_number && (
                <div className="kivi-clinic-information-item clinic-admin_number">
                  <div className="kivi-clinic-information-title">
                    <h6 className="info-title kivicare-clinic-label">{settings.iq_kivicare_clinic_admin_number_label}</h6>
                  </div>
                  <div className="kivi-clinic-information-content">
                    <span className="kivicare-clinic-value">{clinic.admin_number}</span>
                  </div>
                </div>
              )}
              {settings.iq_kivicare_clinic_admin_email === 'yes' && clinic.admin_email && (
                <div className="kivi-clinic-information-item clinic-admin_email">
                  <div className="kivi-clinic-information-title">
                    <h6 className="info-title kivicare-clinic-label">{settings.iq_kivicare_clinic_admin_email_label}</h6>
                  </div>
                  <div className="kivi-clinic-information-content">
                    <span className="kivicare-clinic-value">{clinic.admin_email}</span>
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        <div className="kivi-clinic-bookaction">
          <button className="kivi-button kivi-button-primary kivicare-book-appointment-btn" onClick={() => handleBookClick(clinic)}>
            {__('Book Appointment', 'kivicare-clinic-management-system')}
          </button>
        </div>
      </div>
    </div>
  );
}

// Initialization with Elementor hook
jQuery(window).on('elementor/frontend/init', function () {
  elementorFrontend.hooks.addAction('frontend/element_ready/kivicare_clinic_list.default', function ($scope) {
    const container = $scope.find('.kivicare-clinic-list-container');
    if (container.length) {
      const clinicsData = JSON.parse(container.attr('data-clinics') || '[]');
      const settingsData = JSON.parse(container.attr('data-settings') || '{}');
      const currentUserRole = container.attr('data-current-user-role') || '';
      container.find('.kivicare-loading').remove();
      const root = createRoot(container[0]);
      root.render(<ClinicWidget clinics={clinicsData} settings={settingsData} currentUserRole={currentUserRole} />);
    }
  });
});

// Fallback for frontend and shortcodes
document.addEventListener('DOMContentLoaded', function() {
  // Check if we're in Elementor edit mode
  const isElementorEditMode = typeof elementorFrontend !== 'undefined' && elementorFrontend.isEditMode();
  
  if (!isElementorEditMode) {
    const containers = document.querySelectorAll('.kivicare-clinic-list-container');
    containers.forEach(container => {
      if (!container.hasAttribute('data-rendered')) {
        const clinicsData = JSON.parse(container.getAttribute('data-clinics') || '[]');
        const settingsData = JSON.parse(container.getAttribute('data-settings') || '{}');
        const currentUserRole = container.getAttribute('data-current-user-role') || '';
        container.querySelector('.kivicare-loading')?.remove();
        const root = createRoot(container);
        root.render(<ClinicWidget clinics={clinicsData} settings={settingsData} currentUserRole={currentUserRole} />);
        container.setAttribute('data-rendered', 'true');
      }
    });
  }
});