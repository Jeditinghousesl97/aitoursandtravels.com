  </main><!-- /.admin-content -->
</div><!-- /.admin-wrapper -->

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Sidebar toggle
  document.getElementById('sidebarToggle')?.addEventListener('click', function () {
    document.getElementById('adminSidebar').classList.toggle('collapsed');
    document.querySelector('.admin-content').classList.toggle('expanded');
  });
</script>
</body>
</html>
