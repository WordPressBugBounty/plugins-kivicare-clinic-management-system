import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { __, setLocaleData } from '@wordpress/i18n';
import { KCAlert } from "../../../dashboard/components";
import '@elementor/scss/ElementorWidget.scss';


// Set locale data
setLocaleData(window.kc_frontend?.locale_data || {}, 'kivicare-clinic-management-system');

function DoctorWidget({ doctors: initialDoctors, settings: initialSettings, clinicId: initialClinicId, currentUserRole }) {
  const [doctors, setDoctors] = useState(initialDoctors || []);
  const [settings, setSettings] = useState(initialSettings || {});
  const [clinicId, setClinicId] = useState(initialClinicId || null);
  const [serviceFilter, setServiceFilter] = useState('');
  const [specialtyFilter, setSpecialtyFilter] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentIndex, setCurrentIndex] = useState(0);
  const [showModal, setShowModal] = useState(false);
  const [selectedDoctor, setSelectedDoctor] = useState(null);
  
  // Filter state for manual filtering
  const [appliedFilters, setAppliedFilters] = useState({
    service: '',
    specialty: '',
    search: ''
  });
  
  // Pagination state
  const [currentPage, setCurrentPage] = useState(1);
  const doctorsPerPage = settings.iq_kivicare_doctor_par_page || 5;

  const filteredDoctors = doctors.filter(doctor => {
    const nameMatch = doctor.name.toLowerCase().includes(appliedFilters.search.toLowerCase());
    const emailMatch = doctor.email.toLowerCase().includes(appliedFilters.search.toLowerCase());
    const specialtyMatch = !appliedFilters.specialty || doctor.speciality.toLowerCase() === appliedFilters.specialty.toLowerCase();
    const serviceMatch = !appliedFilters.service || (Array.isArray(doctor.services) && doctor.services.some(s => s.toLowerCase() === appliedFilters.service.toLowerCase()));
    return (nameMatch || emailMatch) && specialtyMatch && serviceMatch;
  });

  const uniqueServices = [...new Set(doctors.flatMap(d => d.services))].filter(Boolean);
  const uniqueSpecialties = [...new Set(doctors.map(d => d.speciality).filter(Boolean))];

  // Pagination calculations
  const totalPages = Math.ceil(filteredDoctors.length / doctorsPerPage);
  const startIndex = (currentPage - 1) * doctorsPerPage;
  const endIndex = startIndex + doctorsPerPage;
  const paginatedDoctors = filteredDoctors.slice(startIndex, endIndex);

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


  const handleBookClick = (doctor) => {
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
    
    setSelectedDoctor(doctor);
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setSelectedDoctor(null);
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

        // Preselect doctor/clinic
        if (selectedDoctor?.id) {
          formContainer.setAttribute('data-selected-doctor-id', String(selectedDoctor.id));
        }
        if (clinicId) {
          formContainer.setAttribute('data-selected-clinic-id', String(clinicId));
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
        
        // Add a small delay to ensure the DOM is ready
        setTimeout(() => {
          window.initBookAppointment();
        }, 100);
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
  }, [showModal, selectedDoctor, clinicId]);

  // Removed carousel functionality - always use grid layout

  if (doctors.length === 0) {
    return <div className="kivicare-no-doctors">{__('No doctors found.', 'kivicare-clinic-management-system')}</div>;
  }

  return (
    <div className="kivi-widget-container">
      {settings.iq_kivicare_doctor_enable_filter === 'yes' && (
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
          {uniqueServices.length > 0 && (
            <div className="kivi-filter-item">
              <select className="kivi-select" value={serviceFilter} onChange={(e) => setServiceFilter(e.target.value.toLowerCase())}>
                <option value="">{__('Filter by Services', 'kivicare-clinic-management-system')}</option>
                {uniqueServices.map((service) => (
                  <option key={service} value={service.toLowerCase()}>
                    {service}
                  </option>
                ))}
              </select>
            </div>
          )}
          <div className="kivi-filter-item">
            <input
              type="text"
              placeholder={__('Search doctor by name, email', 'kivicare-clinic-management-system')}
              className="kivi-input"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <div className="kivi-filter-item">
            <button className="kivi-button kivi-button-primary" onClick={handleFilterClick}>{__('Filter Doctor', 'kivicare-clinic-management-system')}</button>
          </div>
        </div>
      )}

      <div className="kivi-doctor-list">
        {paginatedDoctors.map((doctor) => (
          <div
            key={doctor.id}
            data-name={doctor.name.toLowerCase()}
            data-email={doctor.email.toLowerCase()}
            data-specialty={doctor.speciality.toLowerCase()}
            data-services={doctor.services.join(',').toLowerCase()}
          >
            {renderDoctorCard(doctor, settings, handleBookClick)}
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

function renderDoctorCard(doctor, settings, handleBookClick) {
  return (
    <div className="kivi-doctor">
      {settings.iq_kivicare_doctor_image === 'yes' && (
        <div className="kivi-doctor-image">
          <img src={doctor.image} alt={doctor.name} className="doctor-image" />
        </div>
      )}
      <div className="kivi-doctor-content">
        {settings.iq_kivicare_doctor_name === 'yes' && (
          <div className="doctor-name">
            {settings.iq_kivicare_doctor_name_label && (
              <h6 className="kivicare-doctor-label">{settings.iq_kivicare_doctor_name_label}</h6>
            )}
            <h3 className="kivicare-doctor-name">{doctor.name}</h3>
          </div>
        )}
        {settings.iq_kivicare_doctor_speciality === 'yes' && doctor.speciality && (
          <div className="doctor-speciality">
            {settings.iq_kivicare_doctor_speciality_label && (
              <h6 className="kivicare-doctor-label">{settings.iq_kivicare_doctor_speciality_label}</h6>
            )}
            <p className="kivicare-doctor-speciality">{doctor.speciality}</p>
          </div>
        )}

        <div className="kivi-doctor-information">
          {settings.iq_kivicare_doctor_number === 'yes' && doctor.number && (
            <div className="kivi-doctor-information-item doctor-number">
              <div className="kivi-doctor-information-title">
                <h6 className="info-title kivicare-doctor-label">{settings.iq_kivicare_doctor_number_label}</h6>
              </div>
              <div className="kivi-doctor-information-content">
                <span className="kivicare-doctor-value">{doctor.number}</span>
              </div>
            </div>
          )}
          {settings.iq_kivicare_doctor_email === 'yes' && doctor.email && (
            <div className="kivi-doctor-information-item doctor-email">
              <div className="kivi-doctor-information-title">
                <h6 className="info-title kivicare-doctor-label">{settings.iq_kivicare_doctor_email_label}</h6>
              </div>
              <div className="kivi-doctor-information-content">
                <span className="kivicare-doctor-value">{doctor.email}</span>
              </div>
            </div>
          )}
          {settings.iq_kivicare_doctor_qualification === 'yes' && doctor.qualification && (
            <div className="kivi-doctor-information-item doctor-qualification">
              <div className="kivi-doctor-information-title">
                <h6 className="info-title kivicare-doctor-label">{settings.iq_kivicare_doctor_qualification_label}</h6>
              </div>
              <div className="kivi-doctor-information-content">
                <span className="kivicare-doctor-value">{doctor.qualification}</span>
              </div>
            </div>
          )}
        </div>

        {settings.iq_kivicare_doctor_session === 'yes' && doctor.sessions.length > 0 && (
          <div className="doctor-session">
            <h5 className="kivi-administrator-title kivicare-doctor-label">{settings.iq_kivicare_doctor_session_label}</h5>
            <div className="kivi-appointment-information kivicare-session-grid">
              {doctor.sessions.map((session, idx) => {
                return (
                  <div key={idx} className="kivi-appointment-item kivicare-session-item">
                    <span className="day kivicare-session-day">{session.day}</span>
                    {session.isMultiple ? (
                      <span className="time kivicare-session-time">
                        {session.timeSlots.map((timeSlot, slotIdx) => (
                          <span key={slotIdx} className="kivicare-session-time-slot">
                            {timeSlot}
                            {slotIdx < session.timeSlots.length - 1 ? ', ' : ''}
                          </span>
                        ))}
                      </span>
                    ) : (
                      <span className="time kivicare-session-time">{session.time}</span>
                    )}
                  </div>
                );
              })}
            </div>
          </div>
        )}

        <div className="kivi-doctor-bookaction">
          <button className="kivi-button kivi-button-primary kivicare-book-appointment-btn" onClick={() => handleBookClick(doctor)}>
            {__('Book Appointment', 'kivicare-clinic-management-system')}
          </button>
        </div>
      </div>
    </div>
  );
}

// Initialization with Elementor hook
jQuery(window).on('elementor/frontend/init', function () {
  elementorFrontend.hooks.addAction('frontend/element_ready/kivicare_doctor_list.default', function ($scope) {
    const container = $scope.find('.kivicare-doctor-list-container');
    if (container.length) {
      const doctorsData = JSON.parse(container.attr('data-doctors') || '[]');
      const settingsData = JSON.parse(container.attr('data-settings') || '{}');
      const clinic = container.attr('data-clinic-id');
      const currentUserRole = container.attr('data-current-user-role') || '';
      container.find('.kivicare-loading').remove();  // Remove loading message
      const root = createRoot(container[0]);  // Use createRoot
      root.render(<DoctorWidget doctors={doctorsData} settings={settingsData} clinicId={clinic} currentUserRole={currentUserRole} />);
    }
  });
});

// Fallback for frontend and shortcodes - init on DOMContentLoaded if not in Elementor editor
document.addEventListener('DOMContentLoaded', function() {
  // Check if we're in Elementor edit mode
  const isElementorEditMode = typeof elementorFrontend !== 'undefined' && elementorFrontend.isEditMode();
  
  if (!isElementorEditMode) {
    const containers = document.querySelectorAll('.kivicare-doctor-list-container');
    containers.forEach(container => {
      if (!container.hasAttribute('data-rendered')) {
        const doctorsData = JSON.parse(container.getAttribute('data-doctors') || '[]');
        const settingsData = JSON.parse(container.getAttribute('data-settings') || '{}');
        const clinic = container.getAttribute('data-clinic-id');
        const currentUserRole = container.getAttribute('data-current-user-role') || '';
        container.querySelector('.kivicare-loading')?.remove();
        const root = createRoot(container);
        root.render(<DoctorWidget doctors={doctorsData} settings={settingsData} clinicId={clinic} currentUserRole={currentUserRole} />);
        container.setAttribute('data-rendered', 'true');
      }
    });
  }
});